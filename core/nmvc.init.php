<?php namespace nmvc\internal;

const VERSION = "2.0.0-dev";

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

/**
 * Puts a configuration directive in the configuration file.
 * If configuration already exists, it is not added.
 * @param string $config_var_fqn
 * @param mixed $default_value
 */
function put_configuration_directive($config_var_fqn, $default_value) {
    if (\defined($config_var_fqn))
        return;
    \define($config_var_fqn, $default_value);
    // Add constant to application configuration.
    $config_file_data = file_get_contents(APP_CONFIG);
    $default_value = var_export($default_value, true);
    $namespace = preg_replace('#\\\\[^\\\\]*$#', '', $config_var_fqn);
    $config_var_name = preg_replace('#^([^\\\\]*\\\\)*#', '', $config_var_fqn);
    if ($config_var_name != strtoupper($config_var_name))
        trigger_error("Configuration constants must be upper case, '$config_var_fqn' is not.", \E_USER_ERROR);
    if (preg_match('#namespace\s+' . str_replace("\\",  "\\\\", $namespace) . '\s*{#si', $config_file_data, $match, \PREG_OFFSET_CAPTURE)) {
        // Insert.
        $offset = $match[0][1] + strlen($match[0][0]);
        $config_file_data = substr($config_file_data, 0, $offset)
        . "\r\n    const $config_var_name = $default_value;"
        . substr($config_file_data, $offset);
    } else {
        // Append.
        $config_file_data .= "\r\n\r\nnamespace $namespace {\r\n    const $config_var_name = $default_value;\r\n}\r\n";
    }
    file_put_contents(APP_CONFIG, $config_file_data);
}

/**
 * Reads a $_SERVER variable.
 */
function read_server_var($var_name, $alt_var_name = null) {
    if (!array_key_exists($var_name, $_SERVER)) {
        if ($alt_var_name !== null && array_key_exists($alt_var_name, $_SERVER))
            $var_name = $alt_var_name;
        else
            trigger_error("nanoMVC initialization failed: Required \$_SERVER variable '$var_name' is not set! Webserver/PHP incompability?", \E_USER_ERROR);
    }
    return $_SERVER[$var_name];
}

\call_user_func(function() {
    // Start output buffering.
    \ob_start();
    // Send Powered By Header.
    \header("X-Powered-By: nmvc/" . VERSION, false);
    // Explicitly set the default timezone to UTC.
    \date_default_timezone_set("UTC");
    // Using UTF-8 for everything.
    if (!\extension_loaded("iconv"))
        \trigger_error("nanoMVC initialization failed: The iconv extention is required for UTF-8 support.", \E_USER_ERROR);
    \iconv_set_encoding("internal_encoding", "UTF-8");
    \iconv_set_encoding("output_encoding", "UTF-8");
    // Walk down from the script filename to get the app dir.
    // Note: This method is compatible with symbolic links.
    $app_dir = \realpath(\dirname(\dirname($_SERVER['SCRIPT_FILENAME'])));
    if ($app_dir === false)
        \trigger_error("nanoMVC initialization failed: realpath() error when evaluating APP_DIR.", \E_USER_ERROR);
    // Read configuration, local configuration first if it exist.
    \define("APP_DIR", $app_dir);
    \define("APP_CORE_DIR", dirname(__FILE__));
    // Read configuration.
    $local_config = APP_DIR . "/config.local.php";
    \error_reporting(\E_ALL & ~\E_NOTICE & ~\E_STRICT);
    if (is_file($local_config))
        require $local_config;
    \define("APP_CONFIG", APP_DIR . "/config.php");
    require APP_CONFIG;
    if (modules_using() === null)
        \trigger_error("nanoMVC initialization failed: config.php did not set what modules the application is using.", \E_USER_ERROR);
    // Declaring all configuration directives that is used before the loader
    // has loaded them here.
    put_configuration_directive('nmvc\core\config\DEVELOPER_KEY', "");
    put_configuration_directive('nmvc\core\config\MAINTENANCE_MODE', true);
    put_configuration_directive('nmvc\core\config\FORCE_ERROR_DISPLAY', false);
    put_configuration_directive('nmvc\core\config\FORCE_ERROR_FLAGS', false);
    put_configuration_directive('nmvc\core\config\ERROR_LOG', null);
    put_configuration_directive('nmvc\core\config\PEAR_AUTOLOAD', false);
    put_configuration_directive('nmvc\core\config\TRANSLATION_ENABLED', false);
    put_configuration_directive('nmvc\core\config\TRANSLATION_MIN_Q', 0.4);
    put_configuration_directive('nmvc\core\config\IGNORE_64_BIT_WARNING', false);
    // Evaluate whether NanoMVC is compatible with PHP environment.
    \define("APP_64_BIT", PHP_INT_MAX > 0x7FFFFFFF);
    if (\version_compare(\phpversion(), "5.3.3") < 0)
        \trigger_error("nanoMVC initialization failed: NanoMVC requires PHP version >= 5.3.3.", \E_USER_ERROR);
    if (!APP_64_BIT && !\nmvc\core\config\IGNORE_64_BIT_WARNING)
        \trigger_error("nanoMVC initialization failed: NanoMVC expects 64 bit PHP. Using 32 bit PHP can result in obscure problems like ID sequence exhaustion after 2147483647. This check can be disabled in configuration (IGNORE_64_BIT_WARNING).", \E_USER_ERROR);
    // Check if running as apache child or in script mode.
    \define("REQ_IS_SCRIPT", !\function_exists("apache_get_version") || \apache_get_version() === false);
    if (!REQ_IS_SCRIPT) {
        // Evaluate developer mode based on configuration and cookies.
        $devkey_is_blank = \nmvc\core\config\DEVELOPER_KEY == "";
        $devkey_matches = isset($_COOKIE['NMVC_DEVKEY']) && ($_COOKIE['NMVC_DEVKEY'] === \nmvc\core\config\DEVELOPER_KEY);
        \define("APP_IN_DEVELOPER_MODE", \nmvc\core\config\MAINTENANCE_MODE && ($devkey_is_blank || $devkey_matches));
        // Evaluate application root host, path, port and protocol.
        \define("APP_ROOT_PROTOCOL", (isset($_SERVER["HTTPS"]) && !empty($_SERVER["HTTPS"]))? "https": "http");
        \define("APP_ROOT_HOST", \preg_replace('#:[\d]+$#', "", read_server_var("HTTP_HOST")));
        \define("APP_ROOT_PORT", $server_port = intval(read_server_var('SERVER_PORT')));
        \define("APP_USING_STANDARD_PORT", (APP_ROOT_PROTOCOL == "http" && APP_ROOT_PORT == 80) || (APP_ROOT_PROTOCOL == "https" && APP_ROOT_PORT == 443));
         // Evaluate APP_PATH from PHP_SELF.
        $php_self = read_server_var("PHP_SELF");
        if (!preg_match('#/core/core\.php$#', $php_self))
            trigger_error("nanoMVC initialization failed: PHP_SELF does not end with '/core/core.php'. Make sure the core is installed properly.", \E_USER_ERROR);
        \define("APP_ROOT_PATH", substr($php_self, 0, -strlen("core/core.php")));
        // Determine if this is a proxy request or not, allowing nanomvc to act as a proxy.
        if (isset($_SERVER["REQUEST_URI"]) && \preg_match('#^https?://#', \strtolower($_SERVER["REQUEST_URI"]))) {
            // For proxy requests the local REQ_URL is actually not defined,
            // so this special placeholder will be used.
            \define("REQ_IS_PROXY", true);
            \define("REQ_PROXY_URL", $_SERVER["REQUEST_URI"]);
            \define("REQ_URL", "/proxy");
            \define("REQ_URL_QUERY", REQ_URL);
        } else {
            // Parse the request url which is relatie to the application root path.
            \define("REQ_IS_PROXY", false);
            \define("REQ_PROXY_URL", null);
            \define("REQ_URL", substr(read_server_var("REDIRECT_SCRIPT_URL", "REDIRECT_URL"), strlen(APP_ROOT_PATH) - 1));
            \define("REQ_URL_QUERY", REQ_URL . (isset($_SERVER["REDIRECT_QUERY_STRING"])? "?" . $_SERVER["REDIRECT_QUERY_STRING"]: ""));
        }
    } else {
        $hostname = \gethostname();
        // Script request mode does not have a developer validation mechanism.
        \define("APP_IN_DEVELOPER_MODE", \nmvc\core\config\MAINTENANCE_MODE);
        // Use appropriate dummy data for these constants.
        \define("APP_ROOT_PROTOCOL", "http");
        \define("APP_ROOT_HOST", $hostname !== false? $hostname: "localhost");
        \define("APP_ROOT_PORT", 0);
        \define("APP_USING_STANDARD_PORT", true);
        \define("APP_ROOT_PATH", "/");
        // Allow first argument passed to nanoMVC to be the required path when invoking from command line.
        global $argv;
        $req_url = isset($argv[1])? $argv[1]: "";
        if ($req_url == "" || $req_url[0] != "/")
            $req_url = "/$req_url";
        $query_start = \strpos($req_url, "?");
        if ($query_start !== false) {
            $req_url_query = $req_url;
            $req_query_str = \substr($req_url, $query_start + 1);
            $req_url = \substr($req_url, 0, $query_start);
        } else {
            $req_query_str = "";
            $req_url_query = "";
        }
        \define("REQ_URL", $req_url);
        \define("REQ_URL_QUERY", $req_url_query);
        if (!isset($_GET) || !\is_array($_GET) || \count($_GET) == 0) {
            // Populate the $_GET global automatically.
            $_GET = array();
            \parse_str($req_query_str, $_GET);
        }
    }
    \define("APP_ROOT_URL", APP_ROOT_PROTOCOL . "://" . APP_ROOT_HOST . (APP_USING_STANDARD_PORT? "": ":" . APP_ROOT_PORT) . APP_ROOT_PATH);
    \define("REQ_URL_DIR", dirname(REQ_URL));
    \define("REQ_URL_BASE", basename(REQ_URL));
    // Disable some features in critical core requests.
    \define("REQ_IS_CORE_DEV_ACTION", \strncasecmp(REQ_URL, "/core/action/", \strlen("/core/action/")) == 0);
    // The gettext extention conflicts with nanomvc and must be disabled.
    if (\nmvc\core\config\TRANSLATION_ENABLED && \extension_loaded("gettext"))
        trigger_error("NanoMVC compability error: The Gettext PHP extention is loaded in your installation and must be disabled as it conflicts with the NanoMVC core gettext implementation. Optionally you can disable translation by setting core\config\TRANSLATION_ENABLED to false.", \E_USER_ERROR);
    // \define identifier constants.
    \define("VOLATILE", "VOLATILE");
    \define("INDEXED", "INDEXED");
    \define("NON_INDEXED", "NON_INDEXED");
    \define("INDEXED_UNIQUE", "INDEXED_UNIQUE");
});