<?php namespace melt\internal;

call_user_func(function() {
    // Saving sessions in database. Session data is stored and read in
    // snapshots. No row locking is used when reading since that would result
    // in terrible performance as sessions are implicitly read
    // and updated for all requests.
    \session_set_save_handler(function() {}, function() {
    },function($id) {
        if (REQ_IS_CORE_CONSOLE || defined("MELT_REQUEST_COMPLETE"))
            return \serialize(array());
        // Read snapshot.
        $session_data = \melt\Model::getDataForSelection(
            \melt\core\SessionDataModel::select("session_data")
            ->byKey(array("session_key" => $id))
        );
        return isset($session_data[0][0])? $session_data[0][0]: null;
    }, function($session_key, $binary_session_data) {
        if (REQ_IS_CORE_CONSOLE || defined("MELT_REQUEST_COMPLETE"))
            return;
        // Write.
        $session_data = \melt\core\SessionDataModel::select()
        ->byKey(array("session_key" => $session_key))->forUpdate()->first();
        if ($session_data === null) {
            $session_data = new \melt\core\SessionDataModel();
            $session_data->session_key = $session_key;
        }
        $session_data->session_data = $binary_session_data;
        $session_data->store();
    }, function($id) {
        if (REQ_IS_CORE_CONSOLE || defined("MELT_REQUEST_COMPLETE"))
            return;
        // Destroy.
        \melt\core\SessionDataModel::select()
        ->byKey(array("session_key" => $id))->forUpdate()->unlink();
    }, function($maxlifetime) {
        if (REQ_IS_CORE_CONSOLE || defined("MELT_REQUEST_COMPLETE"))
            return;
        // GC.
        $maxlifetime = intval($maxlifetime);
        $time = time();
        \melt\core\SessionDataModel::select()->where("last_store_attempt")
        ->isLessThan($time - $maxlifetime)->forUpdate()->unlink();
    });
    // Set session ID by get parameter if set. This enables perserving
    // sessions when doing cross domain redirection.
    $cross_domain_session_hopping = isset($_GET["_SESSION_ID"]) && \melt\string\in_range($_GET["_SESSION_ID"], 8, 32);
    if ($cross_domain_session_hopping)
        \session_id($_GET["_SESSION_ID"]);
    // Forward session cookie parameters from configuration.
    $session_domain = \melt\core\config\SESSION_DOMAIN;
    if (!\is_string($session_domain) || $session_domain === "")
        $session_domain = null;
    $secure = \melt\core\config\SESSION_ENFORCE_HTTPS == true;
    \session_set_cookie_params(0, "/", $session_domain, $secure);
    // Using a custom session name based on domain hash to prevent
    // sessions for superset domains to override current domain.
    \session_name("PHPSESSID_" . \substr(\sha1($session_domain, false), 0, 10));
    // Start session.
    \session_start();
    if ($cross_domain_session_hopping) {
        // Redirect-remove the session ID immidiatly to prevent session hijacking from URL sharing.
        unset($_GET["_SESSION_ID"]);
        \melt\request\redirect(\melt\request\url(REQ_URL, $_GET));
    }
});