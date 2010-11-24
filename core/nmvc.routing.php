<?php namespace nmvc\internal;

\call_user_func(function() {
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
    // Handle apache special code pages.
    $redir_status = $_SERVER["REDIRECT_STATUS"];
    if ($redir_status != "200" && $redir_status != null)
        \nmvc\request\show_xyz($redir_status);
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
});