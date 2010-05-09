<?php

namespace nmvc {
    // Core constants.
    const VERSION = "1.2.0";
    define("APP_CORE_DIR", dirname(__FILE__));
    // Standard function to restore the working dir to the core directory.
    function restore_workdir() {
        if (chdir(APP_CORE_DIR) !== true)
            trigger_error("nanoMVC: Could not flip working directory to core.", \E_CORE_ERROR);
    }
    restore_workdir();
    // Send Powered By Header.
    header("X-Powered-By: nMVC/" . VERSION, false);
    // Explicitly set the default timezone if neccessary.
    if (function_exists("date_default_timezone_set"))
        date_default_timezone_set(@date_default_timezone_get());
    // Using UTF-8 for everything.
    iconv_set_encoding("internal_encoding", "UTF-8");
    iconv_set_encoding("output_encoding", "UTF-8");
    // Start session.
    session_start();
    // Find the bootstrap and include the configuration there.
    $path = null;
    foreach (get_included_files() as $path)
    if (substr($path, -13) == "bootstrap.php")
        break;
    if ($path == null)
        trigger_error("nanoMVC: Could not load config.php, bootstrap.php not found in include stack.", \E_CORE_ERROR);
}

namespace nmvc\config {
    // Read configuration.
    define("APP_DIR", dirname($path));
    require APP_DIR . "/config.php";
    // Evaluate developer mode based on configuration and cookies.
    $devkey_is_blank = DEV_KEY == "";
    $devkey_matches = isset($_COOKIE['devkey']) && ($_COOKIE['devkey'] === DEV_KEY);
    define("APP_IN_DEVELOPER_MODE", MAINTENANCE && ($devkey_is_blank || $devkey_matches));
    define("APP_ROOT_HOST", parse_url(ROOT_URL, PHP_URL_HOST));
    define("APP_ROOT_PATH", parse_url(ROOT_URL, PHP_URL_PATH));
    $port = parse_url(ROOT_URL, PHP_URL_PORT);
    if (!$port || $port == "")
        $port = 80;
    define("APP_ROOT_PORT", $port);
}
