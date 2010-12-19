<?php namespace nmvc\http;

const HTTP_METHOD_GET = 0;
const HTTP_METHOD_POST = 1;
const HTTP_METHOD_HEAD = 2;

/**
 * Unhooks the current request from the client by forcing the client
 * to close the connection and setting PHP to ignore souch an abort.
 * Note: Relies on a hack that might stop working in future versions.
 */
function unhook_current_request() {
    \nmvc\request\reset();
    ignore_user_abort(true);
    header("Connection: close");
    header("Content-Encoding: none");
    header("Content-Length: 0");
    ob_start();
    echo " ";
    ob_end_flush();
    flush();
    ob_end_clean();
}

/**
 * Encodes the key,value data with URL encoding. Does not support binary data.
 * @param array $data Key value data to encode.
 * @return array An array mapped like this: 0 => Content Type, 1 => Data.
 */
function make_urlencoded_formdata($data) {
    $data = http_build_query($data);
    return array("application/x-www-form-urlencoded", $data);
}

/**
 * Encodes the key,value data as multipart/form-data. Supports binary data.
 * @param array $data Key value data to encode.
 * @return array An array mapped like this: 0 => Content Type, 1 => Data.
 */
function make_multipart_formdata($data) {
    $boundary = \nmvc\string\random_hex_str(16);
    $encoded_data = "";
    // Encoding POST data as multipart/form-data. Otherwise
    foreach ($data as $key => $val) {
        $key = str_replace('"', '\"', $key);
        $encoded_data .= "--$boundary\r\n";
        $encoded_data .= "Content-Disposition: form-data; name=\"$key\"\r\n";
        $encoded_data .= "Content-Type: application/octet-stream; charset=UTF-8\r\n";
        $encoded_data .= "Content-Transfer-Encoding: binary\r\n\r\n";
        $encoded_data .= $val . "\r\n";
    }
    $encoded_data .= "--$boundary--\r\n";
    return array("multipart/form-data; boundary=" . $boundary, $encoded_data);
}

const HTTP_ERROR_REDIRECT_LIMIT = -1;
const HTTP_ERROR_TIMEOUT = -2;
const HTTP_ERROR_MALFORMED_RESPONSE = -3;

/**
 * Makes a HTTP request for the given URL and returns the data received.
 * @param string $url Absolute URL to send the HTTP request too.
 * @param HTTP_METHOD $method A \nmvc\http\HTTP_METHOD_XXX method for the request.
 * @param array $cookies An array of cookies to send with the request.
 * @param string $user_agent Specify something other than null to not use the default nanoMVC user agent.
 * @param boolean $include_common_headers Set to true to send headers assoicated with normal browsers to make the request look more natural.
 * @param array $contents Specify to send data with the POST request. It should contain an array mapped like this: 0 => Content Type, 1 => Data.
 * @param integer $timeout Time before request times out. Connection attempt
 * and reading/sending data from server can not take longer than this or
 * timeout error will occour.
 * @return mixed The data and response headers returned by the request like this: array(0 => Returned Data, 1 => Response Headers)
 * If the request failed, one of the HTTP_ERROR_ codes will be returned instead.
 */
function request($url, $method = HTTP_METHOD_GET, $cookies = array(), $user_agent = null, $include_common_headers = false, $contents = array(), $timeout = 10) {
    $methods = array(
        HTTP_METHOD_GET => "GET",
        HTTP_METHOD_POST => "POST",
        HTTP_METHOD_HEAD => "HEAD",
    );
    if (!isset($methods[$method]))
        trigger_error("Unknown HTTP method! Not part of \nmvc\http\HTTP_METHOD_XXX.", \E_USER_ERROR);
    $headers = array();
    // Write user agent.
    if ($user_agent === null)
        $user_agent = "nmvc/" . \nmvc\internal\VERSION;
    $headers["User-Agent"] = $user_agent;
    // Include common client headers if requested.
    if ($include_common_headers) {
        $headers["Accept-language"] = "en";
        $headers["Accept"] = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        $headers["Accept-Language"] = "en-us,en;q=0.5";
        $headers["Accept-Charset"] = " ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $headers["Cache-Control"] = "max-age=0";
    }
    // Write cookies to cookie header.
    if (count($cookies) > 0) {
        foreach ($cookies as $k => &$v)
            $v = "$k=$v";
        $headers["Cookie"] = implode($cookies, "; ");
    }
    if ($method === HTTP_METHOD_POST && count($contents) == 2) {
        $headers["Content-Type"] = $contents[0];
        $content = $contents[1];
    } else
        $content = null;
    // Make request, max 8 redirects.
    for ($i = 0; $i < 8; $i++) {
        $response = raw_request($url, $methods[$method], $headers, $content, $timeout);
        if (!is_array($response))
            return $response;
        $status_code = $response[1];
        $return_headers = $response[3];
        if ($method !== HTTP_METHOD_POST && $status_code[0] == "3" && isset($return_headers["Location"])) {
            $url = $return_headers["Location"];
        } else {
            $data_blob = $response[4];
            return array($data_blob, $return_headers);
        }
    }
    return HTTP_ERROR_REDIRECT_LIMIT;
}

/**
 * Makes a RAW HTTP request and returns the result. This function does NOT follow redirects etc.
 * It only makes a single request using sockets. The reusult is stored in an array.
 * @param string $url URL to request. Supports http (default) and https with SSL extention.
 * @param string $method GET, POST, HEAD or any other method the server supports.
 * @param array $headers A key value array of headers you want to use with the request.
 * @param string $data The data you want to send with the request or null to not send data.
 * @param integer $timeout Time before request times out. Connection attempt
 * and reading/sending data from server can not take longer than this or
 * timeout error will occour.
 * @return mixed array(http version, status code, reason phrase, headers (key => value mapped), data blob)
 * or one of HTTP_ERROR_ codes if request failed.
 */
function raw_request($url, $method = "GET", $headers = array(), $data = null, $timeout = 10) {
    $parts = parse_url($url);
    $scheme = isset($parts['scheme'])? $parts['scheme']: 'http';
    if (!isset($parts['host']))
        trigger_error("URL '$url' does not contain host!", \E_USER_ERROR);
    $host = $parts['host'];
    $port = isset($parts['port'])? intval($parts['port']): 'http';
    $path = isset($parts['path'])? $parts['path']: '/';
    $query = isset($parts['query'])? '?' . $parts['query']: '';
    if ($scheme == 'http') {
        $default_port = 80;
        $sock_host = $host;
    } else if ($scheme == 'https') {
        $default_port = 443;
        $sock_host = "ssl://" . $host;
    } else
        trigger_error("raw_request does not understand the protocol: " . $parts['scheme'], \E_USER_ERROR);
    $port = isset($parts['port'])? $parts['port']: $default_port;
    $request_data = $method . " " . $path . $query . " HTTP/1.1\r\n";
    if (!isset($headers["Host"])) {
        $headers["Host"] = $host;
        if ($port != 80)
            $headers["Host"] .=  ':' . $port;
    }
    if (!isset($headers["Connection"]))
        $headers["Connection"] = "Close";
    if (!isset($headers["Content-Length"]) && strlen($data) > 0)
        $headers["Content-Length"] = strlen($data);
    // Do not allow Accept-Encoding header to be overwritten as it controls
    // the internal decoding in raw_request().
    // raw_request() does not support compression.
    $headers["Accept-Encoding"] = "chunked, identity";
    foreach ($headers as $header => $value)
        $request_data .= "$header: " . $value . "\r\n";
    $request_data .= "\r\n" . $data;
    $time_start = microtime(true);
    $fp = fsockopen($sock_host, $port, $errno, $errstr, $timeout);
    if (!$fp)
        return HTTP_ERROR_TIMEOUT;
    fwrite($fp, $request_data);
    $response = "";
    $time_left = $timeout - (microtime(true) - $time_start);
    stream_set_timeout($fp, floor($time_left), round($time_left - floor($time_left)) * 1000);
    while (false !== ($chunk = fgets($fp, 1280)))
        $response .= $chunk;
    if (!feof($fp)) {
        fclose($fp);
        return HTTP_ERROR_TIMEOUT;
    }
    fclose($fp);
    $header_blob_length = strpos($response, "\r\n\r\n");
    if ($header_blob_length === false)
        return HTTP_ERROR_MALFORMED_RESPONSE;
    $header_blob = substr($response, 0, $header_blob_length + 2);
    $data_blob = substr($response, $header_blob_length + 4);
    if (preg_match('#(HTTP/1\..) ([^ ]+) ([^' . "\r\n" . ']+)' . "\r\n#im", $header_blob, $matches) == 0)
        return HTTP_ERROR_MALFORMED_RESPONSE;
    $http_version = $matches[1];
    $status_code = $matches[2];
    $reason_phrase = $matches[3];
    preg_match_all("#([^:\r\n]+): ([^\r\n]*)#", $header_blob, $matches, PREG_SET_ORDER, strlen($matches[0]));
    $headers = array();
    foreach ($matches as $match)
        $headers[$match[1]] = $match[2];
    // Convert chunked transfer encoding to identity if specified.
    $transfer_encoding = @$headers['Transfer-Encoding'];
    if ($transfer_encoding == "chunked") {
        $new_data_blob = "";
        $at = 0;
        while (true) {
            $next_newline = strpos($data_blob, "\r\n", $at);
            if ($next_newline === false)
                break;
            $chunk_length = hexdec(substr($data_blob, $at, $next_newline - $at));
            if ($chunk_length == 0)
                break;
            $new_data_blob .= substr($data_blob, $next_newline + 2, $chunk_length);
            $at = $next_newline + 2 + $chunk_length + 2;
        }
        $data_blob = $new_data_blob;
    }
    return array($http_version, $status_code, $reason_phrase, $headers, $data_blob);
}

