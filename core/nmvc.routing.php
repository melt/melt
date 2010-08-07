<?php namespace nmvc\internal;
// Stuff can be rendered beyond this point, so reset output buffer.
\nmvc\request\reset();
// Parse the request and redirect if the url is invalid.
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
        \nmvc\request\redirect(url($clear_arg));
        exit;
    }
}
// Make sure root location is matched as required.
$invalid_protocol = \nmvc\core\config\REQUIRE_PROTOCOL != null && !preg_match(\nmvc\core\config\REQUIRE_PROTOCOL, APP_ROOT_PROTOCOL);
$invalid_host = \nmvc\core\config\REQUIRE_HOST != null && !preg_match(\nmvc\core\config\REQUIRE_HOST, APP_ROOT_HOST);
$invalid_port = \nmvc\core\config\REQUIRE_PORT != null && !preg_match(\nmvc\core\config\REQUIRE_PORT, APP_ROOT_PORT);
$invalid_path = \nmvc\core\config\REQUIRE_PATH != null && !preg_match(\nmvc\core\config\REQUIRE_PATH, APP_ROOT_PATH);
if ($invalid_protocol || $invalid_host || $invalid_port || $invalid_path) {
    if (APP_ROOT_URL == \nmvc\core\config\REDIRECT_URL)
        trigger_error("Invalid configuration. The client was redirected to the configured URL, but the URL does not match the core\\REQUIRE_* configuration.", \E_USER_ERROR);
    // Redirect if configured.
    if (\nmvc\core\config\REDIRECT_URL != null)
        \nmvc\request\redirect(\nmvc\core\config\REDIRECT_URL);
    // Otherwise show 404 or information.
    if (!APP_IN_DEVELOPER_MODE)
        \nmvc\request\show_404();
    else {
        $debug_match = "<ul>";
        if ($invalid_protocol)
            $debug_match .= "<li>Protocol '" . APP_ROOT_PROTOCOL . "' did not match REQUIRE_PROTOCOL pattern '" . \nmvc\core\config\REQUIRE_PROTOCOL . "'</li>";
        if ($invalid_host)
            $debug_match .= "<li>Host '" . APP_ROOT_HOST . "' did not match REQUIRE_HOST pattern '" . \nmvc\core\config\REQUIRE_HOST . "'</li>";
        if ($invalid_port)
            $debug_match .= "<li>Port '" . APP_ROOT_PORT . "' did not match REQUIRE_PORT pattern '" . \nmvc\core\config\REQUIRE_PORT . "'</li>";
        if ($invalid_path)
            $debug_match .= "<li>Path '" . APP_ROOT_PATH . "' did not match REQUIRE_PATH pattern '" . \nmvc\core\config\REQUIRE_PATH . "'</li>";
        $debug_match .= "</ul>";
        \nmvc\request\info("This request did not match requirements in configuration", "<p>This HTTP server is configured to let the application handle this request, however the applications core\\REQUIRE_* configuration does not match this URL, and no core\\REDIRECT_URL is configured. This request would have been displayed as a 404, however the application is currently in developer mode.</p>" . $debug_match);
    }
    exit;
}
// Handle apache special code pages.
$redir_status = $_SERVER["REDIRECT_STATUS"];
if ($redir_status != "200" && $redir_status != null)
    request\show_xyz($redir_status);
// Stop request here if in developer mode and not developer controller.
if (\nmvc\core\config\MAINTENANCE_MODE && !APP_IN_DEVELOPER_MODE) {
    if (\nmvc\Controller::invokeFromExternalRequest($url_tokens, 'nmvc\core\InternalController'))
        exit;
    \nmvc\core\DeveloperController::invoke("_maintenance_info", array(), true);
    exit;
}
// Invoke all modules before request processors.
foreach (get_all_modules() as $module_name => $module_parameters) {
    $class_name = $module_parameters[0];
    call_user_func(array($class_name, "beforeRequestProcess"));
}
// Inject request into standard MVC handling.
if (\nmvc\Controller::invokeFromExternalRequest($url_tokens))
    exit;
// See if any module is interested in catching the request instead.
foreach (get_all_modules() as $module_name => $module_parameters) {
    $class_name = $module_parameters[0];
    call_user_func(array($class_name, "catchRequest"), $url_tokens);
}
// Finally show 404.
\nmvc\request\show_404();