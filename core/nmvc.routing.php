<?php

namespace nanomvc;

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
    }
    // Change working directory to application/site.
    chdir(APP_DIR);
    if (config\MAINTENANCE && !APP_IN_DEVELOPER_MODE) {
        // Stop requests trying to access site during maintence without beeing developers.
        header("HTTP/1.x 503 Service Unavailable");
        header("Status: 503 Service Unavailable");
        $est = (strlen(config\DOWN_MSG) > 0)? "<p>" . config\DOWN_MSG . "</p>":
                                               "<p>" . __("Please try again in a moment.") . "</p>";
        $topic = "503 Service Unavailable";
        $msg = "<p>" . __("Temporary maintence is in effect so the site is currently not availible.") . "</p>" . $est;
        request\info($topic, $msg);
    }
}

{
    // Parse the request into controllers, actions and arguments.
    $parts = explode("/", substr(REQ_URL, 1));
    // If any arguments are empty the URL is invalid, remove them and
    // redirect the browser. This prevents double URL mapped to the same
    // things so it is good for consistancy and SEO.
    if (count($parts) > 1) {
        $clear_arg = array();
        foreach ($parts as $part)
        if (strlen($part) > 0)
            $clear_arg[] = $part;
        if (count($parts) != count($clear_arg)) {
            $clear_arg = count($clear_arg) > 0? "/" . implode("/", $clear_arg): "";
            request\redirect(url($clear_arg));
            exit;
        }
    }
    // Include default application classes.
    require "app_controller.php";
    require "app_model.php";
    // Application may rewrite the request URL at this point.
    AppController::rewriteRequestUrl($parts);
    // If there is a controller with this name, run it.
    if (!Controller::invoke($parts))
        request\show_404();
    else
        return;
}