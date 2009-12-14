<?php
/**
* ab.Catcher.php
* @desc Provides standard error handling for nanoMVC.
* @desc Configures how nanoMVC should handle errors.
*/

function assert_failed($file, $line, $message) {
	throw new Exception('Assertation failed! '.$message);
}

function exception_handler(Exception $exception) {
	// Working directory might have changed, restore.
	restore_workdir();
	// Restore output buffer.
	@api_misc::ob_reset();
	$h = @fopen("error.log", 'a+');
	$elog_success = false;
	if ($h !== false) {
		$elogstat = @fstat($h);
		$errcode = @substr(md5($elogstat['size'].@time()."incident"),0,6);
		// Format an informing error message and write it to file.
		// Handle the exception trace.
		$trace = $exception->getTrace();
		// Remove any crap on top of trace.
		if (@$trace[0]['file'] == '') {
			unset($trace[0]);
			$trace = array_values($trace);
		}
		$file = @basename($trace[0]['file']);
		$line = @$trace[0]['line'];
		unset($trace[0]);
		global $__mod_dbg, $__mod_dbg_line, $__mod_dbg_file;
		if (isset($__mod_dbg) && $file == basename($__mod_dbg_file)) {
			// Error in specific template. Show it.
			$line -= $__mod_dbg_line;
			$file = " the compiled template: $__mod_dbg line #$line (Compiled as: $file)";
			/*// Correct file location if located in compiled modules.
			global $acms_script_lines;
			if (isset($acms_script_lines[$file])) {
				$last = 0;
				$funcpos = '';
				foreach ($acms_script_lines[$file] as $func => $line_sync) {
					$cur = $line_sync['new'];
					if ($cur > $line) break;
					$funcpos = $func;
				}
				$line_sync = $acms_script_lines[$file][$funcpos];
				$line = ($line - $line_sync['new']) + $line_sync['org'];
				$file = " compiled module " . $file;*/
		} else {
			$file = " system script: $file; line #$line";
		}
		$errraised = " - Raised in $file\n";
		$errmessage = " - Exception Message:\n".$exception->getMessage()."\n";
		$errtrace = " - Call stack:\n";
		foreach ($trace as $key => $t) {
			if (!isset($t['file']) || $t['file'] == '') {
                $t['file'] = '~Internal Location~';
                $t['line'] = 'N/A';
            }
			$errtrace .= '#' . (count($trace) - $key) . ' ' . basename($t['file']) . "(" . $t['line'] . ") " . $t['function'] . '(';
			$first = false;
			foreach ($t['args'] as $arg) {
				if (is_string($arg)) $arg = '"'.substr($arg, 0, 64).'"';
				else if (is_object($arg)) $arg = "[Instance of '".get_class($arg)."']";
				else $arg = strval($arg);
				if (empty($arg)) $arg = 'null';
				if (!$first) $first = true; else $arg = ', ' . $arg;
				$errtrace .= $arg;
			}
			$errtrace .= ")\n";
		}
		$maxlogsize = (CONFIG::$max_logsize * 1000);
		$info = @fstat($h);
		if ($info['size'] < $maxlogsize) {
			$errtime = "-Time of failure: ".date("r")."\n";
			$txtmessage = $errcode.": ".@str_replace("\n", "\r\n\t", $errraised.$errmessage.$errtrace.$errtime)."\r\n";
			if (@fwrite($h, $txtmessage) !== false)
				$elog_success = true;
		}
		@fclose($h);
	}
	if (!devmode) {
		// When not in development, use custom error callback and
		// turn off error reporting for everything that can't be handled.
		$topic = "500 - Internal Server Error";
		$msg = "<p>" . __("When handling your request, this server encountered an internal failure, and was forced to abort. " .
			   "We are sorry for any inconvenience this might have caused you. If this internal failure is temporary, " .
			   "reloading the page (by pressing F5 on your keyboard) might resolve the problem.") . "</p>";
		if ($elog_success === true) {
			$msg .= '<p>' . __("If you are able to contact the administrator, report this error code:") . ' #'.$errcode.'.</p>';
		} else {
			$msg .= '<p>' . __('If you are able to contact the administrator, report that the error log could not be written.') . '</p>';
		}
	} else {
		// When under development, be as informative as possible.
        $topic = "nanoMVC - Exception Caught";
        if (headers_sent()) {
            // Only display text message if headers and possible content has been sent already.
            echo "\r\n\r\n$topic\r\n\r\n$errraised\r\n$errmessage\r\n$errtrace\r\n$errtime";
            exit;
        }
		$msg = '<p>You are identified as an nanoMVC engineer. There was an exception raised in ' . CONFIG::$site_name . '.</p>';
		$errmsgs = explode("\n", "\n" . api_html::escape($errraised) . "\n" . api_html::escape($errmessage) . "\n" . api_html::escape($errtrace));
		$light = false;
		$msg .= '<div style="font:14px monospace;">';
		foreach ($errmsgs as $errmsg) {
			$msg .= '<div style="padding:1px 5px;min-height:15px;';
			$msg .= '">' . $errmsg . '</div>';
		}
		$msg .= '</div>';
	}
    header("HTTP/1.x 500 Internal Server Error");
    header("Status: 500 Internal Server Error");
    api_navigation::info($topic, $msg);
	exit;
}

function error_handler($errno, $errstr, $errfile, $errline) {
	if ($errno == E_USER_ERROR) {
		$e = new Exception("Error of level USER_LEVEL caught: ".$errstr, $errno);
		exception_handler($e);
		exit;break;
	} else {
		// More strict error handling when under development.
		if (Config::$maintence && ($errno == E_WARNING || $errno == E_NOTICE)) {
			// Fetching undefined keys in arrays is valid.
			if (strpos($errstr, "Undefined offset") !== FALSE) return true;
			if (strpos($errstr, "Undefined index") !== FALSE) return true;
			// Ignore any smarty notice.
			if (strpos($errstr, "smarty") !== FALSE) return true;
			$e = new Exception((($errno == E_WARNING)? "Warning": "Notice")." of level USER_LEVEL caught: ".$errstr, $errno);
			exception_handler($e);
			exit;break;
		}
	}
	// Silently bypass internal PHP error handler.
	// Force execution of script.
	return true;
}

function panic($message) {
	throw new Exception("nanoMVC Engine Panic: " . $message);
}

// Never use standard unsafe PHP error handling.
// Show informative messages trough nanoMVC on script Exceptions/Assertations.
assert_options(ASSERT_CALLBACK, 'assert_failed');
set_exception_handler('exception_handler');
set_error_handler('error_handler');

// In maintance mode, nanoMVC should stop ignoring anything but Errors.
if (Config::$maintence)
	error_reporting(E_ALL | E_STRICT);
else
	error_reporting(E_USER_ERROR);




?>