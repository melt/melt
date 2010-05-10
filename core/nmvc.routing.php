<?php namespace nmvc;

// URL Parsing.
{
    $host = strval($_SERVER["HTTP_HOST"]);
    $rurl = strval($_SERVER["REDIRECT_URL"]);
    // Remove :80 if specified on host as this port is implicit.
    if (substr($host, -3) == ":80")
        $host = substr($host, 0, -3);
    $slen = strlen(APP_ROOT_PATH);
    // Parse relevant url parts and prevent dot escape.
    define("REQ_URL", "/" . substr($rurl, $slen));
    define("REQ_URL_DIR", dirname(REQ_URL));
    define("REQ_URL_BASE", basename(REQ_URL));
    define("REQ_URL_QUERY", REQ_URL . (isset($_SERVER["REDIRECT_QUERY_STRING"])? "?" . $_SERVER["REDIRECT_QUERY_STRING"]: ""));
}

// Include all module descriptor classes and call their beforeRequestProcesses.
$config_file_data = null;
foreach (internal\get_all_modules() as $module_name => $module_parameters) {
    $class_name = $module_parameters[0];
    $module_path = $module_parameters[1];
    // Configure module.
    $mod_cfg_path = $module_path . "/config.php";
    if (is_file($mod_cfg_path)) {
        $config_directives = require($mod_cfg_path);
        foreach ($config_directives as $name => $default) {
            $const = "nmvc\\$module_name\\config\\$name";
            if (!defined($const)) {
                define($const, $default);
                // Add constant to application configuration.
                if ($config_file_data === null)
                    $config_file_data = file_get_contents(APP_DIR . "/config.php");
                $default = var_export($default, true);
                if (preg_match('#namespace\s+nmvc\\\\' . $module_name . '\\\\config\s*{#si', $config_file_data, $match, \PREG_OFFSET_CAPTURE)) {
                    // Insert.
                    $offset = $match[0][1] + strlen($match[0][0]);
                    $config_file_data = substr($config_file_data, 0, $offset)
                    . "\r\n\tconst $name = $default;"
                    . substr($config_file_data, $offset);
                } else {
                    // Append.
                    $config_file_data .= "\r\n\r\nnamespace nmvc\\$module_name\\config {\r\n\tconst $name = $default;\r\n}\r\n";
                }
            }
        }
    }
    // Load module.
    $mod_path = $module_path . "/module.php";
    require($mod_path);
    if (!class_exists($class_name))
        trigger_error("nanoMVC: '$class_name' was not declared in '$mod_path'!", \E_USER_ERROR);
    else if (!is_subclass_of($class_name, 'nmvc\Module'))
        trigger_error("nanoMVC: '$class_name' must extend 'nmvc\\Module'! (Declared in '$mod_path')", \E_USER_ERROR);
    call_user_func(array($class_name, "beforeRequestProcess"));
}

// Save the new configuration file if it was modified.
if ($config_file_data !== null)
    file_put_contents(APP_DIR . "/config.php", $config_file_data);

// Special request handling.
{
    // Handle apache special code pages.
    $redir_status = $_SERVER["REDIRECT_STATUS"];
    if ($redir_status != "200" && $redir_status != null)
        request\show_xyz($redir_status);
    // Redirect if the host is invalid.
    if (APP_ROOT_HOST != $host)
        request\redirect(url("/"));
    // Must be same root url.
    if (substr($rurl, 0, $slen) != APP_ROOT_PATH)
        request\show_404();
    // Stop users trying to access site during maintence without beeing developers.
    if (config\MAINTENANCE && !APP_IN_DEVELOPER_MODE) {
        header("HTTP/1.x 503 Service Unavailable");
        header("Status: 503 Service Unavailable");
        $est = (strlen(config\DOWN_MSG) > 0)? "<p>" . config\DOWN_MSG . "</p>":
                                               "<p>" . __("Please try again in a moment.") . "</p>";
        $topic = "503 Service Unavailable";
        $msg = "<p>" . __("Temporary maintence is in effect so the site is currently not availible.") . "</p>" . $est;
        request\info($topic, $msg);
    }
}

// Injecting request in to standard MVC handling.
{
    $url_tokens = explode("/", substr(REQ_URL, 1));
    // If any arguments are empty the URL is invalid, remove them and
    // redirect the browser. This prevents double URL mapped to the same
    // things so it is good for consistancy and SEO.
    if (count($url_tokens) > 1) {
        $clear_arg = array();
        foreach ($url_tokens as $url_token)
        if (strlen($url_token) > 0)
            $clear_arg[] = $url_token;
        if (count($url_tokens) != count($clear_arg)) {
            $clear_arg = count($clear_arg) > 0? "/" . implode("/", $clear_arg): "";
            request\redirect(url($clear_arg));
            exit;
        }
    }
    // If there is a controller with this name, run it.
    if (!Controller::invoke($url_tokens, true)) {
        // See if any module is interested in catching the request instead.
        foreach (internal\get_all_modules() as $module_name => $module_parameters) {
            $class_name = $module_parameters[0];
            call_user_func(array($class_name, "catchRequest"), $url_tokens);
        }
        request\show_404();
    } else
        return;
}