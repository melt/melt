<?php namespace melt\internal;

const INTERNAL_LOCATION = "~Internal Location~";

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
        $trace = \array_values($trace);
    }
    $file = @$trace[0]['file'];
    $line = @$trace[0]['line'];
    unset($trace[0]);
    crash($exception->getMessage(), $file, $line, $trace);
}

function error_handler($errno, $errstr, $errfile, $errline) {
    // Bypass this error if it should not report it.
    if ((\error_reporting() & $errno) == 0)
        return true;
    // Bypass static function should not be abstract, because it's a useful design pattern.
    if ($errno === \E_STRICT && stripos($errstr, "should not be abstract") !== false)
        return true;
    $backtrace = \debug_backtrace();
    unset($backtrace[0]["function"]);
    unset($backtrace[0]["args"]);
    if ($errno === E_USER_ERROR) {
        crash("E_USER_ERROR caught: " . $errstr, $errfile, $errline, $backtrace);
        exit;
    }
    // The developer is not interested in bad vendor code.
    $vendor_path = \str_replace("\\", "/", APP_DIR . "/vendors/");
    foreach (array(array(array('file' => $errfile)), $backtrace) as $backtraces)
    foreach ($backtraces as $call) {
        if (!isset($call['file']))
            continue;
        $file = \str_replace("\\", "/", $call['file']);
        if (\melt\string\starts_with($file, $vendor_path))
            return true;
    }
    $type = get_error_name($errno);
    if ($type === null)
        $type = "E_UNKNOWN";
    crash("$type caught: " . $errstr, $errfile, $errline, $backtrace);
    exit;
}

function remove_cache_headers() {
    if (\headers_sent())
        return;
    // Removes any headers indicating cache status
    // to prevent exception beeing cached.
    \header("Cache-Control:", true);
    \header("Last-Modified:", true);
    \header("Expires:", true);
    \header("Pragma:", true);
    \header("Etag:", true);
}

function development_crash($type, $variables) {
    if (!\melt\core\config\MAINTENANCE_MODE)
        trigger_error("Development Error Caught: " . $type, \E_USER_ERROR);
    \melt\request\reset();
    remove_cache_headers();
    if (!headers_sent()) {
        \header("HTTP/1.x 500 Internal Server Error");
        \header("Status: 500 Internal Server Error");
    }
    $msg = \melt\View::render("/core/deverrors/$type", $variables);
    define("melt_REQUEST_CRASHED", true);
    die("<h1>Development Error $type</h1>" . $msg);
}

/**
 * Returns the top call in stack "file:line" that belongs either
 * to the app or to the module domain. Otherwise returns "CORE".
 * @return string
 */
function get_user_callpoint() {
    $backtrace = debug_backtrace();
    foreach ($backtrace as $call) {
        $file = @$call["file"];
        $domain = get_call_file_domain($file);
        if ($domain == "app" || $domain == "module") {
            if (\melt\core\on_windows())
                $file = \str_replace("\\", "/", $file);
            if (\melt\string\starts_with($file, APP_DIR))
                $file = \substr($file, \strlen(APP_DIR));
            return $file . ":" . @$call["line"];
        }
    }
    return "CORE";
}

/**
 * Returns the domain the given file belongs to.
 * @param string $file
 * @return string
 */
function get_call_file_domain($file) {
    if ($file == null || $file == INTERNAL_LOCATION)
        return "php";
    else if (\preg_match("#[/\\\\]core[/\\\\]#", $file))
        return "core";
    else if (\preg_match("#[/\\\\]modules[/\\\\]#", $file))
        return "module";
    else
        return "app";
}

/**
 * Returns a text formated call signature from a backtrace call array.
 * @param array $call
 * @return string
 */
function get_call_signature($call) {
    $call_signature = "";
    if (isset($call['file']))
        $call_signature .= \basename($call['file']) . " ";
    if (isset($call['line']))
        $call_signature .= "(" . $call['line'] . ") ";
    if (isset($call['function'])) {
        $call_signature .= $call['function'] . '(';
        $first = false;
        if (isset($call['args'])) {
            foreach ($call['args'] as $arg) {
                if (\is_string($arg))
                    $arg = '"' . (\strlen($arg) <= 64? $arg: \substr($arg, 0, 64) . "…") . '"';
                else if (\is_object($arg))
                    $arg = "[Instance of '" . \get_class($arg) . "']";
                else if ($arg === true)
                    $arg = "true";
                else if ($arg === false)
                    $arg = "false";
                else if ($arg === null)
                    $arg = "null";
                else
                    $arg = \strval($arg);
                if (!$first) $first = true; else $arg = ', ' . $arg;
                $call_signature .= $arg;
            }
        }
        $call_signature .= ")";
    }
    return $call_signature;
}

function crash($message, $file, $line, $trace) {
    // Restore output buffer.
    \melt\request\reset();
    remove_cache_headers();
    $errcode = \melt\string\random_alphanum_str(6);
    // Log the error.
    $errtrace = "__Stack:\n";
    $html_errtrace = "__Stack:\n";
    $first_trace = true;
    foreach ($trace as $key => $call) {
        if (!isset($call['file']) || $call['file'] == '') {
            $call['file'] = INTERNAL_LOCATION;
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
        } else if (@$prev_function == "trigger_error" || @$prev_file == INTERNAL_LOCATION || \basename(@$prev_file) == "imports.php") {
            $file = @$call['file'];
            $line = @$call['line'];
            $prev_file = @$call['file'];
            $prev_function = @$call['function'];
        } else {
            $prev_file = null;
            $prev_function = null;
        }
        // Format the trace line.
        $trace_line = '#' . (\count($trace) - $key) . ' ' . get_call_signature($call);
        $errtrace .= "$trace_line\n";
        switch (get_call_file_domain($call["file"])) {
        case "php":
        case "core":
            $html_errtrace .= \melt\html\escape($trace_line) . "\n";
            break;
        case "module":
            $html_errtrace .= "<span style=\"color: green;\">" . \melt\html\escape($trace_line) . "</span>\n";
            break;
        default:
            $html_errtrace .= "<span style=\"color: blue;\">" . \melt\html\escape($trace_line) . "</span>\n";
            break;
        }
    }
    $errlocation = "__Path: " . REQ_URL . "\n";
    $errraised = "__File: $file; line #$line\n";
    // The message cannot be larger than 8K. Prevents error log flooding.
    $errmessage = "__Messsage: " . substr($message, 0, 8000) . "\n";
    if (\melt\core\config\ERROR_LOG === false) {
        // No error logging.
    } else if (\melt\core\config\ERROR_LOG == null) {
        \error_log(\str_replace("\n", ";", "Exception caught: " . $errraised . $errmessage . $errtrace));
    } else {
        \chdir(APP_DIR);
        $log_entry = "Exception Caught " . date("r") . "\n\n$errraised\n$errmessage\n$errtrace\nError tag: #$errcode\n\n\n";
        \file_put_contents(\melt\core\config\ERROR_LOG, $log_entry, \FILE_APPEND);
    }
    if (!APP_IN_DEVELOPER_MODE && !\melt\core\config\FORCE_ERROR_DISPLAY) {
        // Do not unsafly print error information for non developers.
        $topic = "500 - Internal Server Error";
        $msg = "<p>" . __("The server encountered an internal error and failed to process your request. Please try again later. If this error is temporary, reloading the page might resolve the problem.") . "</p>"
               . '<p>' . __("If you are able to contact the administrator, report this error tag:") . ' #' . $errcode . '.</p>';
    } else {
        // Show error information for developers.
        $topic = "Melt Framework - Exception Caught";
        // If it's too late to set the right content type, use text error.
        // Text errors are always used in script request mode.
        $use_texterror = REQ_IS_CLI;
        if (!$use_texterror && \headers_sent()) {
            foreach (\headers_list() as $header) {
                $ct_header = "content-type: ";
                $text_html = "text/html";
                if (\strtolower(\substr($header, 0, \strlen($ct_header))) == \strtolower($ct_header)) {
                    $use_texterror = \substr($header, \strlen($ct_header), \strlen($text_html)) != $text_html;
                    break;
                }
            }
        }
        // Show code sample if it can.
        $errsample = null;
        if (!$use_texterror && is_file($file)) {
            $zero_offseted_line = $line - 1;
            // Don't read more than 10 MB.
            $file_lines = \explode("\n", \file_get_contents($file, null, null, 0, 10000000));
            // Show two lines below and two lines above.
            $top_line = $zero_offseted_line - 2;
            $file_lines = \array_slice($file_lines, $top_line > 0? $top_line: 0, 5, true);
            if (\count($file_lines) > 0) {
                \end($file_lines);
                $pad_len = \strlen(\key($file_lines) + 1);
                foreach ($file_lines as $line => &$file_line)
                    $file_line = " " . \htmlentities(str_pad($line + 1, $pad_len, "0", STR_PAD_LEFT) . ": " . \str_replace("\t", "    ", \rtrim($file_line)), null, "UTF-8");
                $file_lines[$zero_offseted_line] = "<b style=\"color:red;\">" . $file_lines[$zero_offseted_line] . "</b>";
                $errsample = "__Sample:\n" . \implode("\n", $file_lines) . "\n\n";
            }
        }
        if (!$use_texterror) {
            $errtrace = $html_errtrace;
            $errmessage = \melt\html\escape($errmessage);
        }
        $msg = "$errlocation\n$errraised\n$errmessage\n$errsample$errtrace\nError tag: #$errcode";
        if ($use_texterror)
            die("\n\n$topic\n\n" . $msg);
        $msg = "<pre>$msg</pre>";
    }
    if (!\headers_sent()) {
        \header("HTTP/1.x 500 Internal Server Error");
        \header("Status: 500 Internal Server Error");
    }
    \define("melt_REQUEST_CRASHED", true);
    die("<h1>" . $topic . "</h1>" . $msg);
}

\call_user_func(function() {
    // Never use standard unsafe PHP error handling.
    // Show informative messages trough Melt Framework on script Exceptions/Assertations.
    \assert_options(ASSERT_CALLBACK, '\melt\internal\assert_failed');
    \set_exception_handler('\melt\internal\exception_handler');
    \set_error_handler('\melt\internal\error_handler');
    // Catch all errors in maintence mode or if forcing error display.
    if (\is_integer(\melt\core\config\FORCE_ERROR_FLAGS))
        \error_reporting(\melt\core\config\FORCE_ERROR_FLAGS);
    else if (APP_IN_DEVELOPER_MODE || \melt\core\config\FORCE_ERROR_DISPLAY)
        \error_reporting(E_ALL | E_STRICT);
    else
        \error_reporting(E_USER_ERROR);
    // Check that the database is configured.
    if (!REQ_IS_CORE_CONSOLE && \melt\db\config\HOST == null)
        development_crash("no_database", array());
});