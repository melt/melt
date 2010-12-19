<?php namespace nvmc\internal;

call_user_func(function() {
    // Saving sessions in database.
    \session_set_save_handler(function() {}, function() {
    },function($id) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return \serialize(array());
        // Read.
        $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->first();
        return $session_data !== null? $session_data->session_data: null;
    }, function($session_key, $binary_session_data) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return;
        // Write.
        $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($session_key)->first();
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
        \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->unlink();
    }, function($maxlifetime) {
        if (REQ_IS_CORE_DEV_ACTION || defined("NMVC_REQUEST_COMPLETE"))
            return;
        // GC.
        $maxlifetime = intval($maxlifetime);
        $time = time();
        \nmvc\core\SessionDataModel::select()->where("last_store_attempt")->isLessThan($time - $maxlifetime)->unlink();
    });
    // Forward session cookie parameters from configuration.
    $session_domain = \is_string(\nmvc\core\config\SESSION_DOMAIN);
    if (!\is_string($session_domain) || $session_domain === "")
        $session_domain = APP_ROOT_HOST;
    $secure = \nmvc\core\config\SESSION_ENFORCE_HTTPS == true;
    \session_set_cookie_params(0, "/", $session_domain, $secure);
    // Start session.
    \session_start();
});