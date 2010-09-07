<?php namespace nmvc\internal;

function get_error_name($error_number) {
    $error_map = array(
        \E_ERROR => "E_ERROR",
        \E_WARNING => "E_WARNING",
        \E_PARSE => "E_PARSE",
        \E_NOTICE => "E_NOTICE ",
        \E_CORE_ERROR => "E_CORE_ERROR",
        \E_CORE_WARNING => "E_CORE_WARNING",
        \E_COMPILE_ERROR => "E_COMPILE_ERROR",
        \E_COMPILE_WARNING => "E_COMPILE_WARNING",
        \E_USER_ERROR => "E_USER_ERROR",
        \E_USER_WARNING => "E_USER_WARNING",
        \E_USER_NOTICE => "E_USER_NOTICE",
        \E_STRICT => "E_STRICT",
        \E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
        \E_DEPRECATED => "E_DEPRECATED",
        \E_USER_DEPRECATED => "E_USER_DEPRECATED",
        \E_ALL => "E_ALL",
    );
    return isset($error_map[$error_number])? $error_map[$error_number]: null;
}

function assert_failed($file, $line, $message) {
    throw new \Exception('Assertation failed! ' . $message);
}

function exception_handler(\Exception $exception) {
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
    crash($exception->getMessage(), $file, $line, $trace);
}

function error_handler($errno, $errstr, $errfile, $errline) {
    // Bypass this error if it should not report it.
    if ((error_reporting() & $errno) == 0)
        return true;
    // Bypass static function should not be abstract, because it's a useful design pattern.
    if ($errno == \E_STRICT && substr($errstr, -22) == "should not be abstract")
        return true;
    $backtrace = debug_backtrace();
    unset($backtrace[0]["function"]);
    unset($backtrace[0]["args"]);
    if ($errno == E_USER_ERROR) {
        crash("E_USER_ERROR caught: " . $errstr, $errfile, $errline, $backtrace);
        exit;
    }
    // The developer is not interested in bad vendor code.
    $vendor_path = APP_DIR . "/vendors/";
    foreach (array(array(array('file' => $errfile)), $backtrace) as $backtraces)
    foreach ($backtraces as $call) {
        if (!isset($call['file']))
            continue;
        $file = \str_replace("\\", "/", $call['file']);
        if (\nmvc\string\starts_with($file, $vendor_path))
            return true;
    }
    $type = get_error_name($errno);
    if ($type === null)
        $type = "E_UNKNOWN";
    crash("$type caught: " . $errstr, $errfile, $errline, $backtrace);
    exit;
}

const INTERNAL_LOCATION = "~Internal Location~";

function development_crash($type, $variables) {
    if (!\nmvc\core\config\MAINTENANCE_MODE)
        trigger_error("Development Error Caught: " . $message, \E_USER_ERROR);
    \nmvc\request\reset();
    if (!headers_sent()) {
        header("HTTP/1.x 500 Internal Server Error");
        header("Status: 500 Internal Server Error");
    }
    $msg = \nmvc\View::render("/core/deverrors/$type", $variables);
    die("<h1>Development Error $type</h1>" . $msg);
}

function crash($message, $file, $line, $trace) {
    // Restore output buffer.
    \nmvc\request\reset();
    $errcode = \nmvc\string\random_alphanum_str(6);
    // Log the error.
    $errtrace = "__Stack:\n";
    $html_errtrace = "__Stack:\n";
    $first_trace = true;
    foreach ($trace as $key => $call) {
        if (!isset($call['file']) || $call['file'] == '') {
            $call['file'] = '~Internal Location~';
            $call['line'] = 'N/A';
        }
        /* Keep track of previous function to move trace forward if
         * currently located on a trigger_error or internal location
         * or imports alias which is not interesting as it's very unlikely
         * to be the real cause of the error. */
        if ($first_trace) {
            $prev_file = @$call['file'];;
            $prev_function = @$call['function'];
            $first_trace = false;
        } else if (@$prev_function == "trigger_error" || @$prev_file == INTERNAL_LOCATION || basename(@$prev_file) == "imports.php") {
            $file = @$call['file'];
            $line = @$call['line'];
            $prev_file = @$call['file'];
            $prev_function = @$call['function'];
        } else {
            $prev_file = null;
            $prev_function = null;
        }
        // Format the trace line.
        $trace_line = '#' . (count($trace) - $key) . ' ' . basename($call['file']) . "(" . $call['line'] . ") ";
        if (isset($call['function'])) {
            $trace_line .= $call['function'] . '(';
            $first = false;
            if (isset($call['args'])) {
                foreach ($call['args'] as $arg) {
                    if (is_string($arg))
                        $arg = '"' . (strlen($arg) <= 64? $arg: substr($arg, 0, 64) . "…") . '"';
                    else if (is_object($arg))
                        $arg = "[Instance of '".get_class($arg)."']";
                    else
                        $arg = strval($arg);
                    if (empty($arg)) $arg = 'null';
                    if (!$first) $first = true; else $arg = ', ' . $arg;
                    $trace_line .= $arg;
                }
            }
            $trace_line .= ")";
        }
        $errtrace .= "$trace_line\n";
        if (\preg_match("#[/\\\\]core[/\\\\]#", $call['file']) || $call['file'] == INTERNAL_LOCATION)
            $html_errtrace .= escape($trace_line) . "\n";
        else if (\preg_match("#[/\\\\]modules[/\\\\]#", $call['file']))
            $html_errtrace .= "<span style=\"color: green;\">" . escape($trace_line) . "</span>\n";
        else
            $html_errtrace .= "<span style=\"color: blue;\">" . escape($trace_line) . "</span>\n";
            
    }
    $errlocation = "__Path: " . REQ_URL . "\n";
    $errraised = "__File: $file; line #$line\n";
    // The message cannot be larger than 8K. Prevents error log flooding.
    $errmessage = "__Messsage: " . substr($message, 0, 8000) . "\n";
    error_log(str_replace("\n", ";", "Exception caught: " . $errraised . $errmessage . $errtrace));
    if (!APP_IN_DEVELOPER_MODE && !\nmvc\core\config\FORCE_ERROR_DISPLAY) {
        // Do not unsafly print error information for non developers.
        $topic = "500 - Internal Server Error";
        $msg = "<p>" . __("The server encountered an internal error and failed to process your request. Please try again later. If this error is temporary, reloading the page might resolve the problem.") . "</p>"
               . '<p>' . __("If you are able to contact the administrator, report this error tag:") . ' #' . $errcode . '.</p>';
    } else {
        // Show error information for developers.
        $topic = "nanoMVC - Exception Caught";
        // If it's too late to set the right content type, use text error.
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
        // Show code sample if it can.
        $errsample = null;
        if (!$use_texterror && is_file($file)) {
            $zero_offseted_line = $line - 1;
            // Don't read more than 10 MB.
            $file_lines = explode("\n", file_get_contents($file, null, null, 0, 10000000));
            // Show two lines below and two lines above.
            $top_line = $zero_offseted_line - 2;
            $file_lines = array_slice($file_lines, $top_line > 0? $top_line: 0, 5, true);
            if (count($file_lines) > 0) {
                end($file_lines);
                $pad_len = strlen(key($file_lines) + 1);
                foreach ($file_lines as $line => &$file_line)
                    $file_line = " " . \htmlentities(str_pad($line + 1, $pad_len, "0", STR_PAD_LEFT) . ": " . str_replace("\t", "    ", rtrim($file_line)), null, "UTF-8");
                $file_lines[$zero_offseted_line] = "<b style=\"color:red;\">" . $file_lines[$zero_offseted_line] . "</b>";
                $errsample = "__Sample:\n" . implode("\n", $file_lines) . "\n\n";
            }
        }
        if (!$use_texterror) {
            $errtrace = $html_errtrace;
            $errmessage = escape($errmessage);
        }
        $msg = "$errlocation\n$errraised\n$errmessage\n$errsample$errtrace\nError tag: #$errcode";
        if ($use_texterror)
            die("\n\n$topic\n\n" . $msg);
        $msg = "<pre>$msg</pre>";
    }
    if (!headers_sent()) {
        header("HTTP/1.x 500 Internal Server Error");
        header("Status: 500 Internal Server Error");
    }
    die("<h1>" . $topic . "</h1>" . $msg);
}

// Never use standard unsafe PHP error handling.
// Show informative messages trough nanoMVC on script Exceptions/Assertations.
assert_options(ASSERT_CALLBACK, '\nmvc\internal\assert_failed');
set_exception_handler('\nmvc\internal\exception_handler');
set_error_handler('\nmvc\internal\error_handler');

// Catch all errors in maintence mode or if forcing error display.
if (is_integer(\nmvc\core\config\FORCE_ERROR_FLAGS))
    error_reporting(\nmvc\core\config\FORCE_ERROR_FLAGS);
else if (APP_IN_DEVELOPER_MODE || \nmvc\core\config\FORCE_ERROR_DISPLAY)
    error_reporting(E_ALL | E_STRICT);
else
    error_reporting(E_USER_ERROR);

