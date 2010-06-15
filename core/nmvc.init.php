<?php

namespace nmvc {
    // Core constants.
    const VERSION = "1.5.2";
    define("APP_CORE_DIR", dirname(__FILE__));
    // Standard function to restore the working dir to the core directory.
    function restore_workdir() {
        if (chdir(APP_CORE_DIR) !== true)
            trigger_error("nanoMVC: Could not flip working directory to core.", \E_USER_ERROR);
    }
    restore_workdir();
    // Send Powered By Header.
    header("X-Powered-By: nmvc/" . VERSION, false);
    // Explicitly set the default timezone if neccessary.
    if (function_exists("date_default_timezone_set"))
        date_default_timezone_set(@date_default_timezone_get());
    // Using UTF-8 for everything.
    iconv_set_encoding("internal_encoding", "UTF-8");
    iconv_set_encoding("output_encoding", "UTF-8");
    // Start session.
    session_start();
    // Walk down from the script filename to get the app dir.
    // Note: This method is compatible with symbolic links.
    $app_dir = dirname(dirname($_SERVER['SCRIPT_FILENAME']));
}

namespace nmvc\config {
    /**
     * Configures the non-core modules the application is using
     * (once, in config.php) and then returns them.
     * @return array Configured modules the application is using.
     */
    function modules_using() {
        static $use_modules = null;
        if ($use_modules === null)
            $use_modules = func_get_args();
        else
            return $use_modules;
    }
    // Read configuration, local configuration first if it exist.
    define("APP_DIR", $app_dir);
    $local_config = APP_DIR . "/config.local.php";
    define("APP_CONFIG", is_file($local_config)? $local_config: APP_DIR . "/config.php");
    require APP_CONFIG;
    if (modules_using() === null)
        trigger_error("nanoMVC: config.php did not set what modules the application is using.", \E_USER_ERROR);
    // Evaluate developer mode based on configuration and cookies.
    $devkey_is_blank = DEV_KEY == "";
    $devkey_matches = isset($_COOKIE['devkey']) && ($_COOKIE['devkey'] === DEV_KEY);
    define("APP_IN_DEVELOPER_MODE", MAINTENANCE && ($devkey_is_blank || $devkey_matches));
    $root_host = parse_url(ROOT_URL, PHP_URL_HOST);
    $root_port = parse_url(ROOT_URL, PHP_URL_PORT);
    $root_port = ($root_port != "" && $root_port != "80")? ":$root_port": "";
    define("APP_ROOT_HOST", $root_host . $root_port);
    define("APP_ROOT_PATH", parse_url(ROOT_URL, PHP_URL_PATH));
    $port = parse_url(ROOT_URL, PHP_URL_PORT);
    if (!$port || $port == "")
        $port = 80;
    define("APP_ROOT_PORT", $port);
}
