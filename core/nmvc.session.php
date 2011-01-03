<?php namespace nvmc\internal;

call_user_func(function() {
    // Saving sessions in database. Session data is stored and read in
    // snapshots. No row locking is used when reading since that would result
    // in terrible performance as sessions are implicitly read
    // and updated for all requests.
    \session_set_save_handler(function() {}, function() {
    },function($id) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return \serialize(array());
        // Read snapshot.
        $session_data = \nmvc\Model::getDataForSelection(
        \nmvc\core\SessionDataModel::select("session_data")
        ->where("session_key")->is($id)->limit(1));
        return isset($session_data[0][0])? $session_data[0][0]: null;
    }, function($session_key, $binary_session_data) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return;
        // Write.
        $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($session_key)->forUpdate()->first();
        if ($session_data === null) {
            $session_data = new \nmvc\core\SessionDataModel();
            $session_data->session_key = $session_key;
        }
        $session_data->session_data = $binary_session_data;
        $session_data->store();
    }, function($id) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return;
        // Destroy.
        \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->forUpdate()->unlink();
    }, function($maxlifetime) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return;
        // GC.
        $maxlifetime = intval($maxlifetime);
        $time = time();
        \nmvc\core\SessionDataModel::select()->where("last_store_attempt")->isLessThan($time - $maxlifetime)->forUpdate()->unlink();
    });
    // Set session ID by get parameter if set. This enables perserving
    // sessions when doing cross domain redirection.
    if (isset($_GET["_SESSION_ID"]) && \nmvc\string\in_range($_GET["_SESSION_ID"], 8, 32))
        \session_id($_GET["_SESSION_ID"]);
    // Forward session cookie parameters from configuration.
    $session_domain = \is_string(\nmvc\core\config\SESSION_DOMAIN);
    if (!\is_string($session_domain) || $session_domain === "")
        $session_domain = APP_ROOT_HOST;
    $secure = \nmvc\core\config\SESSION_ENFORCE_HTTPS == true;
    \session_set_cookie_params(0, "/", $session_domain, $secure);
    // Start session.
    \session_start();
});