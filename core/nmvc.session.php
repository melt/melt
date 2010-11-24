<?php namespace nvmc\internal;
\session_set_save_handler(function() {}, function() {
},function($id) {
    if (REQ_IS_CORE)
        return \serialize(array());
    // Read.
    $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->first();
    return $session_data !== null? $session_data->session_data: null;
}, function($session_key, $binary_session_data) {
    if (REQ_IS_CORE)
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
    if (REQ_IS_CORE)
        return;
    // Destroy.
    \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->unlink();
}, function($maxlifetime) {
    if (REQ_IS_CORE)
        return;
    // GC.
    $maxlifetime = intval($maxlifetime);
    $time = time();
    \nmvc\core\SessionDataModel::select()->where("last_store_attempt")->isLessThan($time - $maxlifetime)->unlink();
});
\session_start();
if (\nmvc\core\config\DOMAIN_WIDE_SESSION && !isset($_COOKIE['PHPSESSID'])) {
    // Apply domain wide session cookie by overriding the PHPSESSID header.
    $cookie_domain = \preg_replace('#^[^\.]+#', '', APP_ROOT_HOST);
    if (\substr_count($cookie_domain, ".") < 2)
        $cookie_domain = APP_ROOT_HOST;
    \header("Set-Cookie: PHPSESSID=" . \session_id() . "; path=/; domain=$cookie_domain", true);
}
// Make sure sessions are written before we loose object instancing capability.
\register_shutdown_function(function() {
    // Move write close to the end of shutdown function chain.
    // This enables other shutdown functions to modify session data.
    register_shutdown_function(function() {
        \session_write_close();
    });
});