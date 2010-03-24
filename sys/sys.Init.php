<?php

function pre_panic($reason = "") {
    header('HTTP/1.0 500 Internal Server Error');
    header('Content-Length: 0');
    header('Connection: close');
    ob_clean();
    die("500 Internal Server Error " . $reason);
}
set_exception_handler('pre_panic');

// Standard function to restore the working dir to the system directory.
function restore_workdir() {
    if (chdir(dirname(__FILE__)) !== true)
        // Failed to flip working directory.
        pre_panic("Could not flip working directory to system.");
}
restore_workdir();

// Buffer control.
ob_implicit_flush(true);

// Evaluate version.
define('nmvc_version', '0.5.0');

// Define the core directory.
define("CORE_DIR", dirname(__FILE__));

// Send Powered By Header
header("X-Powered-By: nanoMVC/".nmvc_version, false);

// Start session.
session_start();


?>
