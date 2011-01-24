<?php namespace nmvc\request;

/**
* @desc Attempts to reset the output buffer to the default state
* @desc by throwing away all buffered data and ending all stacked buffers.
*/
function reset() {
    // Remove previous buffers.
    $info = ob_get_status(false);
    $level = intval(@$info['level']);
    for (;$level > 1; $level--)
        ob_end_clean();
    // Reset to default content type.
    if (!headers_sent())
        header('Content-Type: text/html');
    if (intval(ob_get_length()) > 0)
        ob_clean();
    // Reset the application layout.
    \nmvc\View::reset_app_layout();
    // Always using using a single buffer level to enable reset at any point.
    ob_start();
}

/**
 * Use this function when you have a library configured to accept requests
 * by itself. (Handler usually called rpc.php)
 * It will disable nanoMVC error handling and forward the request there.
 * Note: Does not disable \Exception handling. That would be pointless.
 * @param string $php_file Path to php file to pass execution to.
 * @return void Does not return.
 */
function forward($php_file) {
    restore_error_handler();
    chdir(dirname($php_file));
    require(basename($php_file));
    exit;
}

/**
* @desc Displays an informative page that display information for http status code xyz.
*/
function show_xyz($xyz, $name = null, $message = null) {
    $xyz = intval($xyz);
    if ($xyz == 404)
        show_404();
    $status_map = array(
        400 => array("Bad Request", "The syntax of the request was not understood by the server."),
        401 => array("Not Authorised", "The request needs user authentication"),
        402 => array("Payment Required", "Reserved for future use."),
        403 => array("Forbidden", "The server refused to fulfill the request."),
        404 => array("Not Found", "The document/file requested was not found."),
        405 => array("Method Not Allowed", "The method specified in the Request-Line is not allowed for the specified resource."),
        406 => array("Not Acceptable", "The resource requested is only capable of generating response entities which have content characteristics not specified in the accept headers sent in the request."),
        407 => array("Proxy Authentication Required", "The request first requires authentication with the proxy."),
        408 => array("Request Timeout", "The client failed to send a request in the time allowed by the server."),
        409 => array("Conflict", "The request was unsuccessful due to a conflict in the state of the resource."),
        410 => array("Gone", "The resource requested is no longer available and no forwarding address is available."),
        411 => array("Length Required", "The server will not accept the request without a valid Content-Length header field."),
        412 => array("Precondition Failed", "A precondition specified in one or more Request-Header fields returned false."),
        413 => array("Request Entity Too Large", "The request was unsuccessful because the request entity is larger than the server will allow."),
        414 => array("Request URI Too Long", "The request was unsuccessful because the URI specified is longer than the server is willing to process."),
        415 => array("Unsupported Media Type", "The request was unsuccessful because the entity of the request is in a format not supported by the requested resource for the method requested."),
        416 => array("Requested Range Not Satisfiable", "The request included a Range request-header field, and not any of the range-specifier values in this field overlap the current extent of the selected resource, and also the request did not include an If-Range request-header field."),
        417 => array("Expectation Failed", "The expectation given in the Expect request-header could not be fulfilled by the server."),
        500 => array("Internal Server Error", "The request was unsuccessful due to an unexpected condition encountered by the server."),
        501 => array("Not Implemented", "The request was unsuccessful because the server can not support the functionality needed to fulfill the request."),
        502 => array("Bad Gateway", "The server received an invalid response from the upstream server while trying to fulfill the request."),
        503 => array("Service Unavailable", "The request was unsuccessful due to the server being down or overloaded."),
        504 => array("Gateway Timeout", "The upstream server failed to send a request in the time allowed by the server."),
        505 => array("HTTP Version Not Supported", "The server does not support or is not allowing the HTTP protocol version specified in the request."),
    );
    if (!isset($status_map[$xyz]))
        throw new \Exception("show_xyz() could not handle the HTTP status code $xyz becouse it was unrecognized!");
    $status = $status_map[$xyz];
    $desc = ($name == null)? $status_map[$xyz][0]: $name;
    $info = ($message == null)? "<p>" . $status_map[$xyz][1] . "</p>": $message;
    reset();
    if (!headers_sent()) {
        header("HTTP/1.0 $xyz $desc");
        header("Status: $xyz $desc");
    }
    info("$xyz - $desc", $info);
}

/**
* @desc Returns a not modified response.
* @return Void Does not return, exits the request when done.
*/
function show_not_modified() {
    reset();
    header("HTTP/1.x 304 Not Modified");
    header("Status: 304 Not Modified");
    header("Cache-Control:");
    header("Expires:");
    exit;
}

/**
* @desc Displays an informative page that reads that the request is 404.
* @return Void Does not return, exits the request when done.
*/
function show_404() {
    reset();
    if (!headers_sent()) {
        header("HTTP/1.x 404 Not Found");
        header("Status: 404 Not Found");
    }
    $topic = __("404 - Page not found");
    $msg = '<br /><br /><span style="font-family: monospace;">' . url(REQ_URL) . '</span>';
    if (APP_IN_DEVELOPER_MODE && defined("REQ_REWRITTEN_PATH")) {
        if (is_string(REQ_REWRITTEN_PATH))
            $msg .= '<br /><br />' . __('Developer info: The request was rewritten by AppController::rewriteRequest into:') . '<br /><br /><span style="font-family: monospace;">' . url(REQ_REWRITTEN_PATH) . '</span>';
        else
            $msg .= '<br /><br />' . __('Developer info:  The request was rewritten to 404 by AppController::rewriteRequest') . '<br /><br />';
    }
    $msg = "<p>" . __("The page you requested does not exist on this server: %s", $msg) . "</p>";
    $msg .= "<p><a href=\"" . url("/") . "\">" . __("Go to our main page.") . "</a></p>";
    info($topic, $msg);
    exit;
}

/**
* @desc Displays an informative page that reads that the request was invalid.
* @desc String $info Additional information that will be displayed as reason request failed.
* @return Void Does not return, exits the request when done.
*/
function show_invalid($reason = null) {
    // Prefer exceptions in developer mode.
    if (APP_IN_DEVELOPER_MODE)
        trigger_error("Invalid request raised, reason specified: '$reason'", \E_USER_ERROR);
    reset();
    if (!headers_sent()) {
        header("HTTP/1.0 400 Bad Request");
        header("Status: 400 Bad Request");
    }
    $uri = @$_SERVER['REQUEST_URI'];
    $topic = __("400 - Invalid request");
    $msg = "<p>" . __("Your request could not be understood. The application considers the given input from your browser exceptional and incompatible. Try using other browsers and see if the problem persists.") . "</p>";
    $msg .= "<p>" . __("There might also be a problem with the web application in which case you can help by reporting your issue to the site administrator.") . "</p>";
    if ($reason != null)
        $msg .= "<p>" . __("Reported reason:") . "</p><p style=\"padding: 4px;background-color:#eeeeee; font-family: monospace;\">$reason</p>";
    info($topic, $msg);
    exit;
}

/**
* @desc Displays information with the standard info template (first time).
* @param string $topic Topic of information page.
* @param string $body HTML formated body of information page.
* @return Does not return, exits the request when done.
*/
function info($topic, $body) {
    reset();
    InfoController::invoke("_show", array($topic, $body), true);
    exit;
}

/**
 * Returns true if the current request is invoked by AJAX.
 * Detecting this is useful in cases where this affects
 * if redirection should take place and format of return data.
 * Detection is done by matching the X-Requested-With: XMLHttpRequest header.
 * @return boolean
 */
function is_ajax() {
    return \array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

/**
* @desc Will attempt to abort execution and redirect client to url.
* @param string $url URL to redirect to (eg. http://example/page)
*/
function redirect($url) {
    reset();
    header("Location: " . $url);
    exit;
}

/**
* @desc Will go back from this request, either to Referer if local or site index.
* @return void Does not return.
*/
function go_back() {
    $referer = @$_SERVER['HTTP_REFERER'];
    $blen = strlen(APP_ROOT_URL);
    if (substr($referer, 0, $blen) == APP_ROOT_URL)
        redirect($referer);
    else
        redirect(url('/'));
}


/**
* @param boolean $clean_get Set this to true to clean the current URL from any get arguments.
* @return string Returns the current URL.
*/
function current_url($clean_get = false) {
    if ($clean_get) {
        $rquri = explode("?", @$_SERVER['REQUEST_URI'], 2);
        $rquri = $rquri[0];
    } else $rquri = @$_SERVER['REQUEST_URI'];
    return "http://" . @$_SERVER['HTTP_HOST'] . urldecode($rquri);
}

/**
* @desc Returns a key/value array that represents the query part of the given URL.
* @return Array Empty array of url is invalid or doesn't contain any key/value pairs.
*/
function get_url_query($url) {
    $query = parse_url($url, PHP_URL_QUERY);
    $ret = array();
    $kvs = explode('&', $query);
    foreach ($kvs as $kv) {
       $kv = explode("=", $kv);
       $key = $kv[0]; unset($kv[0]);
       $val = implode("=", $kv);
       if (!isset($ret[$key])) $ret[$key] = $val;
    }
    return $ret;
}

/**
* @desc Creates a local URL from given path.
* @param String $path A local path, eg /etc/lol.png
* @param Array $get An optional array of keys and values to point to in get part.
* @return String A clean, non relative, formated URL to local destination.
*/
function url($path, $get = null) {
    if (strlen($path) == 0 || $path[0] != '/')
        $path = '/' . $path;
    $path = (substr(APP_ROOT_URL, -1) == '/'? substr(APP_ROOT_URL, 0, -1): APP_ROOT_URL) . $path;
    return $get === null? $path: create_url($path, $get);
}

/**
* @desc Constructs a safe URL by merging with optional additional GET args.
* @param String $url Where URL should point. Any get args here may be overridden by $get.
* @param Array $get An optional array of keys and values to point to in get part.
* @return String A clean, non relative, formated URL to destination.
*/
function create_url($url, $get = null) {
    if (strpos($url, "?") !== FALSE) {
        if (!is_array($get)) $get = array();
        $url = explode("?", $url);
        if (count($url) != 2)
            trigger_error("'" . $url . " is not a valid URL!", \E_USER_ERROR);
        $gets = explode("&", $url[1]);
        foreach ($gets as $gp) {
           $gp = explode("=", $gp);
           $key = $gp[0]; unset($gp[0]);
           $val = implode("=", $gp);
           if (!isset($get[$key])) $get[$key] = $val;
        }
        $url = $url[0];
    }
    if ($get != null && count($get) > 0) {
        $url .= "?";
        foreach ($get as $key => $val)
            if (!empty($val))
                $url .= urlencode($key) . "=" . urlencode($val) . "&";
        $url = substr($url, 0, -1);
    }
    return $url;
}

/**
* @desc Dedicates this entire request to send the requested file.
* @param string $filepath  The path to the file to transmit.
* @param string $mime [Optional] The resource type mime identifer.
* @param string $filename [Optional] If not null, the resource will be sent as an attachment with this default filename.
* @return Does not return, will kill this request on completion.
*/
function send_file($filepath, $mime = 'application/octet-stream', $filename = null) {
    if (headers_sent())
        throw new \Exception("Headers has already been sent! Unable to transmit file '$filepath'.");
    reset();
    if (!is_file($filepath))
        show_404();
    // Cache control headers.
    $mf = filemtime($filepath);
    // 304 Not Modified Verification.
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
        $ims = strtotime($ims);
        if ($ims !== false && $ims >= $mf)
            show_not_modified();
    }
    $filesize = filesize($filepath);
    $etag = dechex($mf) . dechex($filesize);
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'];
        $inm = explode(',', $inm);
        foreach ($inm as $mtag)
            if ($etag == $mtag)
                show_not_modified();
    }
    // Set cache headers
    header('Last-Modified: ' . date('r', $mf));
    header('ETag: ' . $etag);
    header('Content-Type: ' . $mime);
    if ($filename != NULL)
        header('Content-Disposition: attachment; filename='.$filename);
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $filesize);
    // Send data.
    $r = readfile($filepath);
    if ($r === FALSE)
        throw new \Exception("Could not access cached file '$path'.");
    exit;
}

/**
 * Finalizes this request and sends the specified data in json format
 * with correct headers.
 * @param mixed $json_data Data to encode and transmit.
 */
function send_json_data($data) {
    \nmvc\request\reset();
    header("Content-Type: application/json");
    die(json_encode($data));
}

/**
 * Returns TRUE if the given local URL is a member of the current request URL.
 */
function current_url_in($local_url) {
    if (REQ_URL == "/")
        return $local_url == REQ_URL;
    $slash_cnt_cur = substr_count(REQ_URL, "/");
    $slash_cnt = substr_count($local_url, "/");
    if ($slash_cnt >= 2 && $slash_cnt_cur > $slash_cnt)
        return \nmvc\string\starts_with(REQ_URL, $local_url);
    else if ($slash_cnt_cur == $slash_cnt)
        return $local_url == REQ_URL;
    else
        return false;
}
