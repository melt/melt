<?php namespace nvmc\internal;
// Disable sessions for core features.
if (\substr(REQ_URL, 0, 6) == "/core/")
    return;
\session_set_save_handler(function() {}, function() {
},function($id) {
    // Read.
    $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->first();
    return $session_data !== null? $session_data->session_data: null;
}, function($session_key, $binary_session_data) {
    // Write.
    $session_data = \nmvc\core\SessionDataModel::select()->where("session_key")->is($session_key)->first();
    if ($session_data === null) {
        $session_data = new \nmvc\core\SessionDataModel();
        $session_data->session_key = $session_key;
    }
    $session_data->session_data = $binary_session_data;
    $session_data->store();
}, function($id) {
    // Destroy.
    \nmvc\core\SessionDataModel::select()->where("session_key")->is($id)->unlink();
}, function($maxlifetime) {
    // GC.
    $maxlifetime = intval($maxlifetime);
    $time = time();
    \nmvc\core\SessionDataModel::select()->where("last_store_attempt").lessThan($time - $maxlifetime)->unlink();
});
\session_start();
// Make sure sessions are written before we loose object instancing capability.
\register_shutdown_function(function() {
    // Move write close to the end of shutdown function chain.
    // This enables other shutdown functions to modify session data.
    register_shutdown_function(function() {
        \session_write_close();
    });
});