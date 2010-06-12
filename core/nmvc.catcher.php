<?php namespace nmvc\internal;

function assert_failed($file, $line, $message) {
    throw new \Exception('Assertation failed! ' . $message);
}

function exception_handler(\Exception $exception) {
    // Restore output buffer.
    \nmvc\request\reset();
    $elog_success = false;
    $errcode = \nmvc\string\random_alphanum_str(6);
    // Format an informing error message and write it to file.
    // Handle the exception trace.
    $trace = $exception->getTrace();
    // Remove any crap on top of trace.
    if (@$trace[0]['file'] == '') {
        unset($trace[0]);
        $trace = array_values($trace);
    }
    $file = @$trace[0]['file'];
    $line = @$trace[0]['line'];
    unset($trace[0]);
    $file = ": $file; line #$line";
    $errraised = " - Raised in$file\n";
    $errmessage = " - Message:\n" . $exception->getMessage() . "\n";
    $errtrace = " - Stack:\n";
    foreach ($trace as $key => $t) {
        if (!isset($t['file']) || $t['file'] == '') {
            $t['file'] = '~Internal Location~';
            $t['line'] = 'N/A';
        }
        $errtrace .= '#' . (count($trace) - $key) . ' ' . basename($t['file']) . "(" . $t['line'] . ") " . $t['function'] . '(';
        $first = false;
        if (isset($t['args'])) {
            foreach ($t['args'] as $arg) {
                if (is_string($arg))
                    $arg = '"' . (strlen($arg) <= 64? $arg: substr($arg, 0, 64) . "…") . '"';
                else if (is_object($arg))
                    $arg = "[Instance of '".get_class($arg)."']";
                else
                    $arg = strval($arg);
                if (empty($arg)) $arg = 'null';
                if (!$first) $first = true; else $arg = ', ' . $arg;
                $errtrace .= $arg;
            }
        }
        $errtrace .= ")\n";
    }
    // Log the error.
    error_log(str_replace("\n", ";", "Exception caught: " . $errraised . $errmessage . $errtrace));
    if (!APP_IN_DEVELOPER_MODE) {
        // Do not unsafly print error information for non developers.
        $topic = "500 - Internal Server Error";
        $msg = "<p>" . __("The server encountered an internal error and failed to process your request. Please try again later. If this error is temporary, reloading the page might resolve the problem.") . "</p>"
               . '<p>' . __("If you are able to contact the administrator, report this error tag:") . ' #' . $errcode . '.</p>';
    } else {
        // Show error information for developers.
        $topic = "nanoMVC - Exception Caught";
        $use_texterror = false;
        if (headers_sent()) {
            foreach (headers_list() as $header) {
                $ct_header = "content-type: ";
                $text_html = "text/html";
                if (strtolower(substr($header, 0, strlen($ct_header))) == strtolower($ct_header)) {
                    $use_texterror = substr($header, strlen($ct_header), strlen($text_html)) != $text_html;
                    break;
                }
            }
        }
        if ($use_texterror) {
            // Only output pure text
            die("\r\n\r\n$topic\r\n\r\n$errraised\r\n$errmessage\r\n$errtrace\r\nError tag: #$errcode");
        } else {
            $msg = '<pre>';
            $msg .= "\n" . \escape($errraised) . "\n" . \escape($errmessage) . "\n" . \escape($errtrace) . "\nError tag: #$errcode";
            $msg .= '</pre>';
        }
    }
    if (!headers_sent()) {
        header("HTTP/1.x 500 Internal Server Error");
        header("Status: 500 Internal Server Error");
    }
    die("<h1>" . $topic . "</h1>" . $msg);
}

function error_handler($errno, $errstr, $errfile, $errline) {
    if ($errno == E_USER_ERROR) {
        throw new \Exception("E_USER_ERROR caught: " . $errstr, $errno);
        exit;
    }
    // We can bypass this error, just notify in developer mode.
    if (!APP_IN_DEVELOPER_MODE)
        return true;
    // Fetching undefined keys in arrays is not exceptional.
    if (strpos($errstr, "Undefined offset") !== FALSE)
        return true;
    if (strpos($errstr, "Undefined index") !== FALSE)
        return true;
    // Connection timed out is expected and not an exceptional event.
    if (strpos($errstr, "Connection timed out") !== FALSE)
        return true;
    // Failing to delete the output buffer is expected when ob_close is
    // called just to be sure.
    if (strpos($errstr, "failed to delete buffer") !== FALSE)
        return true;
    // Yes, nanoMVC uses static abstract functions, which is normally bad,
    // but useful in this Model implementation.
    if (strpos($errstr, "Static function ") !== FALSE
    && strpos($errstr, " should not be abstract") !== FALSE)
        return true;
    $error_map = array(
        E_WARNING => "E_WARNING",
        E_NOTICE => "E_NOTICE ",
        E_USER_ERROR => "E_USER_ERROR",
        E_USER_WARNING => "E_USER_WARNING",
        E_USER_NOTICE => "E_USER_NOTICE",
        E_STRICT => "E_STRICT",
        E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
        E_DEPRECATED => "E_DEPRECATED",
        E_USER_DEPRECATED => "E_USER_DEPRECATED",
    );
    $type = isset($error_map[$errno])? $error_map[$errno]: "E_UNKNOWN";
    throw new \Exception("$type caught: " . $errstr, $errno);
    exit;
}

// Never use standard unsafe PHP error handling.
// Show informative messages trough nanoMVC on script Exceptions/Assertations.
assert_options(ASSERT_CALLBACK, '\nmvc\internal\assert_failed');
set_exception_handler('\nmvc\internal\exception_handler');
set_error_handler('\nmvc\internal\error_handler');

// Catch all errors in maintence mode.
if (\nmvc\config\MAINTENANCE)
    error_reporting(E_ALL | E_STRICT);
else
    error_reporting(E_USER_ERROR);

