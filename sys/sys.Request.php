<?php

function _handle_request() {
    // Handle apache special code pages.
    $redir_status = $_SERVER["REDIRECT_STATUS"];
    if ($redir_status != "200" && $redir_status != null)
        api_navigation::show_xyz($redir_status);
    $host = strval($_SERVER['HTTP_HOST']);
    $rurl = strval($_SERVER['REDIRECT_URL']);
    // Must be same host.
    if (Config::$root_host != $host)
        api_navigation::show_invalid("The host '" . htmlentities($host) . "' is not hosted by this server.");
    // Must be same root url.
    $slen = strlen(CONFIG::$root_path);
    if (substr($rurl, 0, $slen) != CONFIG::$root_path)
        api_navigation::show_404();
    // Parse relevant url part and prevent dot escape.
    define('REQURL', '/' . substr($rurl, $slen));
    define('REQURLDIR', dirname(REQURL));
    define('REQURLBASE', basename(REQURL));
    define('REQURLQUERY', REQURL . (isset($_SERVER['REDIRECT_QUERY_STRING'])? '?' . $_SERVER['REDIRECT_QUERY_STRING']: ''));
    // Change working directory to application/site.
    chdir(APP_DIR);
    if (Config::$maintence && REQURL == '/dev/setkey') {
        // Allow all requests to a special URL that sets the developer cookie for 10 years.
        if (isset($_POST['devkey'])) {
            setcookie("devkey", $_POST['devkey'], intval(time() + 60 * 60 * 24 * 365.242199 * 10), CONFIG::$root_path);
            header("Location: " . CONFIG::$root_path);
        } else {
            $head = ('<title>Set Developer Key</title>');
            $body = ('<form method="post" action=""><input name="devkey" type="password" /><input type="submit" /></form>');
            api_html::write($head, $body);
        }
    } else if (Config::$maintence && !devmode) {
        // Stop requests trying to access site in developer mode.
        header("HTTP/1.x 503 Service Unavailable");
        header("Status: 503 Service Unavailable");
        $est = (!empty(Config::$downshedule))? "<p>" . Config::$downshedule . "</p>":
                                               "<p>" . __("Please try again in a moment.") . "</p>";
        $topic = "503 Service Unavailable";
        $msg = "<p>" . __("Temporary maintence is in effect so the site is currently not availible.") . "</p>" . $est;
        api_navigation::info($topic, $msg);
    } else {
        // Pass request to application.
        api_application::execute(REQURL);
    }
    exit;
}

_handle_request();

?>
