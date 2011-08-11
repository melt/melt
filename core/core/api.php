<?php namespace melt\core;

const melt_CORE_FORK_TIMEOUT = 10;

/**
 * Forks a function call, allowing parallell execution.
 * Note that forking has a relativly high overhead in terms of
 * both CPU and memory so it should be avoided unless neccessary.
 * Also note that fork only works in environments with a webserver
 * configured to handle multiple requests. In other enviroments the fork
 * will timeout after 5 seconds without throwing an exception.
 * @param callback $callback Callback function.
 * @param array $parameters Array of parameters.
 * @see call_user_func_array
 */
function fork($callback, $parameters = array()) {
    if (!\is_callable($callback))
        \trigger_error("The callback '$callback' is invalid!", \E_USER_ERROR);
    // Execute fork.
    $headers = array(
        "Host" => APP_ROOT_HOST,
        "User-Agent" => "Melt Framework/" . \melt\internal\VERSION . " (Internal Fork)",
    );
    $rpc_payload = \melt\string\simple_crypt(\serialize(array(
        "callback" => $callback,
        "parameters" => $parameters,
        "time" => time(),
    )), get_fork_key());
    $base_path = APP_ROOT_PATH;
    if (\substr($base_path, -1) == "/")
        $base_path = \substr($base_path, 0, -1);
    $loopback_addr = req_is_ipv4()? "127.0.0.1": "::1";
    $server_port = \intval($_SERVER["SERVER_PORT"]);
    $server_addr = $_SERVER["SERVER_ADDR"];
    // Using a socket directly so we can open and close as quickly as possible.
    $host = APP_ROOT_HOST;
    $cookie_header = APP_IN_DEVELOPER_MODE? "\r\nCookie: MELT_DEVKEY=" . config\DEVELOPER_KEY: "";
    $request_data = "POST $base_path/core/callback/fork HTTP/1.1\r\nHost: $host"
    . "\r\nContent-Type: text/plain"
    . "\r\nContent-Length: " . \strlen($rpc_payload)
    . $cookie_header
    . "\r\n\r\n$rpc_payload";
    $binary_server_addr = @\inet_pton($server_addr);
    if ($binary_server_addr === false)
        \trigger_error("Fork failed, could not parse server address \"$server_addr\".", \E_USER_ERROR);
    $stream = \fsockopen(\strlen($binary_server_addr) > 4? "[$server_addr]": $server_addr, $server_port, $errno, $errstr, melt_CORE_FORK_TIMEOUT);
    \fwrite($stream, $request_data);
    \stream_set_timeout($stream, 10);
    // Just grab the first chunk with the status code and close the
    // connection since the callback enables ignore user abort before
    // sending any headers.
    $chunk = \fgets($stream, 128);
    if ($chunk !== false) {
        $status = \preg_match('#^[^ ]+ ([^ ]+)#', $chunk, $matches);
        $status = @$matches[1];
        if ($status == "200")
            return;
        $status = "Server returned HTTP $status.";
    } else
        $status = "Loopback connection closed/timeout.";
    trigger_error(__FUNCTION__ . " failed! $status", \E_USER_ERROR);
}

/**
 * Forks a function call, allowing parallell execution.
 * Like core\fork() this is expensive and likley even more expensive as
 * the apache request handling usually has PHP instances ready to go while
 * this function starts a PHP process from scratch.
 * In terms of blocking however this could be faster as it returns
 * immediately after the process is started.
 * @return void
 */
function script_fork($callback, $parameters = array()) {
    if (!\is_callable($callback))
        \trigger_error("The callback '$callback' is invalid!", \E_USER_ERROR);
    if (!\is_executable(\melt\core\config\PHP_BINARY))
        \trigger_error("PHP_BINARY is not executable (" . \melt\core\config\PHP_BINARY . ")", \E_USER_ERROR);
    $callback_payload = \melt\string\base64_alphanum_encode(\serialize(array($callback, $parameters)));
    $slash = on_windows()? "\\": "/";
    $cmdline = \escapeshellarg(\melt\core\config\PHP_BINARY) . " " . \escapeshellarg(APP_DIR . $slash . "core" . $slash . "core.php") . " /core/callback/script_fork/" . $callback_payload;
    // Asyncronously execute command.
    if (on_windows())
        pclose(popen("start \"\" /B $cmdline", "r"));
    else
        exec("$cmdline > /dev/null &");
}

/**
 * Returns the internal fork key used to validate fork requests.
 * @internal
 * @return string
 */
function get_fork_key() {
    static $fork_key = null;
    $fork_key_path = APP_DIR . "/.forkkey";
    if ($fork_key === null) {
        if (\file_exists($fork_key_path)) {
            $fork_key = \file_get_contents($fork_key_path);
            if (\preg_match('#^[0-9a-f]{16,16}$#', $fork_key))
                return $fork_key;
        }
        $fork_key = \strtolower(\melt\string\random_hex_str(16));
        \file_put_contents($fork_key_path, $fork_key);
    }
    return $fork_key;
}

/**
 * Returns true if the current request is a fork.
 * @return boolean
 */
function req_is_fork() {
    return \defined("REQ_IS_FORK") && REQ_IS_FORK;
}

/**
 * Returns true if the current request is IPV4.
 * @return boolean
 */
function req_is_ipv4() {
    static $is_ipv4 = null;
    if ($is_ipv4 === null) 
        $is_ipv4 = \preg_match('#^\d{1,3}(\.\d{1,3}){3,3}$#', $_SERVER["SERVER_ADDR"]) != 0;
    return $is_ipv4;
}

/**
 * Unhooks the current request from the client by forcing the client
 * to close the connection and setting PHP to ignore souch an abort.
 * Note: Relies on a hack that might stop working in future versions.
 */
function req_unhook() {
    \melt\request\reset();
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
* @desc Returns TRUE if arrays are recursivly equal, otherwise FALSE.
* @desc Also returns FALSE if any input is not of array type.
*/
function compare_arrays($array1, $array2) {
    if (!is_array($array1) || !is_array($array2) || (count($array1) != count($array2)))
        return false;
    foreach ($array1 as $key => $val) {
        if (!array_key_exists($key, $array2))
            return false;
        $val2 = $array2[$key];
        if (is_array($val)) {
            if (!compare_arrays($val, $val2))
                return false;
        } else if ($val != $val2) {
            return false;
        }
    }
    return true;
}

/**
 * Like array_merge_recursive but does not create new arrays
 * by merging colliding values, instead replacing the value with
 * the latter choise.
 * @see http://se2.php.net/manual/en/function.array-merge-recursive.php
 * @author mark dot roduner at gmail dot com
 * @return array
 */
function array_merge_recursive_distinct() {
    $arrays = func_get_args();
    $base = array_shift($arrays);
    if (!is_array($base))
        $base = empty($base)? array(): array($base);
    foreach ($arrays as $append) {
        if (!is_array($append)) $append = array($append);
        foreach ($append as $key => $value) {
            if(!array_key_exists($key, $base) and !is_numeric($key)) {
                $base[$key] = $append[$key];
                continue;
            }
            if (is_array($value) or is_array($base[$key]))
                $base[$key] = array_merge_recursive_distinct($base[$key], $append[$key]);
            else if (is_numeric($key))
                if(!in_array($value, $base)) $base[] = $value;
            else
                $base[$key] = $value;
        }
    }
    return $base;
}

/**
 * Recursivly deletes all files and directories in given path.
 * If delete fails the fail callback will be called and the function
 * will return FALSE. If no fail callback is set an E_USER_ERROR will
 * be raised.
 * @param type $path Path to file or directory to delete.
 * @param callback $fail_callback Optional: Callback that will be invoked
 * if deleting path fails. The first argument is the path that was failed
 * to be deleted.
 * @return bool True on success.
 */
function unlink_recursive($path, $fail_callback = null) {
    if ($fail_callback === null) {
        $fail_callback = function($path) {
            trigger_error("Could not delete \"$path\".", E_USER_ERROR);
        };
    }
    if (is_file($path)) {
        if (@unlink($path) === false) {
            $fail_callback($path);
            return false;
        }
    } else {
        foreach (scandir($path) as $node) {
            if ($node === "." || $node === "..")
                continue;
            if (!unlink_recursive("$path/$node"))
                return false;
        }
        if (@rmdir($path) === false) {
            $fail_callback($path);
            return false;
        }
    }
    return true;
}

/**
 * Search the given directory for files which has a path relative to given
 * directory which matches the given regex pattern.
 * If $directory is not a directory, an empty array will be returned.
 * @param string $directory The directory to search.
 * @param integer $pattern Regex pattern to match with the relative paths
 * of the traversed files, or NULL to match all files.
 * @param boolean $recurse Set to false to disable recursion.
 * @return array Files matching the pattern.
 */
function grep($directory, $pattern = null, $recurse = true) {
    static $in_recurse = false;
    static $skip_charachers;
    $reset_in_recurse = false;
    if (!$in_recurse) {
        if (!\is_dir($directory))
            return array();
        $directory = \realpath($directory) . "/";
        $skip_charachers = \strlen($directory);
        $in_recurse = $reset_in_recurse = true;
    }
    $ret = array();
    $dirhandle = \opendir($directory);
    while (false !== ($nodename = \readdir($dirhandle))) {
        if ($nodename[0] == ".")
            continue;
        $subpath = $directory . $nodename;
        if (\is_dir($subpath)) {
            if ($recurse)
                $ret = \array_merge($ret, grep($subpath . "/", $pattern));
        } else if ($pattern == null || \preg_match($pattern, $subpath))
            $ret[] = \substr($subpath, $skip_charachers);
    }
    \closedir($dirhandle);
    if ($reset_in_recurse)
        $in_recurse = false;
    return $ret;
}

/**
 * Specifies that module is required to proceed with request.
 * It will check if it exists and satisfies given version.
 * If it doesn't, the function will terminate the request in either a 404 or
 * a friendly message for site developers.
 * @param string $module_name Module name to check for, eg "html".
 * @param string $min_version NULL for no version checkor or a minimum version eg "1.5.3"
 */
function require_module($module_name, $min_version = null) {
    if (!module_loaded($module_name, $min_version)) {
        if (APP_IN_DEVELOPER_MODE) {
            $of = ($min_version != null)? " of '$min_version'": "";
            request\show_invalid("Module '$module_name'$of not installed but required.");
        } else
            \melt\request\show_404();
    }
}


/**
 * Returns true if the specified module exists and is at least given version.
 * @param string $module_name Module name to check for, eg "html".
 * @param string $min_version NULL for no version checkor or a minimum version eg "1.5.3"
 */
function module_loaded($module_name, $min_version = null) {
    $module_class_name = 'melt\\' . $module_name . '\\' . \melt\string\underline_to_cased($module_name) . "Module";
    if (class_exists($module_class_name)) {
        if ($min_version !== null) {
            $module_version = call_user_func(array($module_class_name, "getVersion"));
            return version_compare($module_version, $min_version, ">=");
        }
        return true;
    }
    return false;
}

/**
 * Requests shared data from other modules by entry name.
 * <b>ANY ENTRY YOUR MODULE REQUIRES SHOULD HAVE A WELL DOCUMENTED FORMAT
 * THAT IS SPECIFIED IN YOUR CONFIG FILE.</b>
 * <i>see the url mapper module for additional reference</i>
 * @param string $entry_name Entry name of data to request.
 * @return array Module names mapped to the shared data they broadcast.
 */
function require_shared_data($entry_name) {
    static $entry_cache = array();
    if (!isset($entry_cache[$entry_name])) {
        $shared_data = array();
        $func_name = "bcd_" . $entry_name;
        foreach (array(\melt\internal\get_all_modules(), array('Application' => array('melt\AppController'))) as $module_class_names)
        foreach ($module_class_names as $module_name => $module) {
            $module_clsname = $module[0];
            if (method_exists($module_clsname, $func_name)) {
                $mod_shared_data = call_user_func(array($module_clsname, $func_name));
                if (is_array($mod_shared_data) && count($mod_shared_data) > 0)
                    $shared_data[$module_name] = $mod_shared_data;
            }
        }
        return $entry_cache[$entry_name] = $shared_data;
    } else
        return $entry_cache[$entry_name];
}

/**
 * Generates a classic UL menu navigation structure and returns it as HTML.
 * This function is integrated with the userx access system and will skip
 * menu items that the user does not have access too.
 * @see userx\RestrictedController::_canAccess
 * @param string $current_css_class What 'current' menu items (li's) should use.
 * @param array $menu_structure A menu structure that consists of labels mapped
 * to an URL and a current matching regex (without modifiers), separated by ",".
 * @param array $current_tokens Array that will have the current path tokens
 * appended onto it.
 * @param array $parent_output Used internally. Set this to null.
 * @return string
 */
function generate_ul_navigation($menu_structure, $current_css_class = "current", &$current_tokens = array(), &$parent_output = null) {
    $has_match = false;
    $first_url = null;
    $has_userx_module = \melt\core\module_loaded("userx");
    if ($parent_output === null)
        $output = '<ul class="nav">';
    else
        $output = "<ul>";
    foreach ($menu_structure as $label => $path_match) {
        $label = escape($label);
        $match_here = false;
        $child_tree = "";
        if (\is_array($path_match)) {
            list($match_here, $url_here) = generate_ul_navigation($path_match, $current_css_class, $current_tokens, $child_tree);
        } else {
            $match_regex_start = strpos($path_match, ",");
            $match_regex = substr($path_match, $match_regex_start + 1);
            $match_here = preg_match("#$match_regex#", REQ_URL) == 1;
            $url_here = substr($path_match, 0, $match_regex_start);
            if ($match_here)
                array_merge(array($label), $current_tokens);
        }
        $has_match = $has_match || $match_here;
        if ($has_userx_module && !\melt\userx\RestrictedController::canAccess($url_here, \melt\userx\get_user()))
            continue;
        if ($first_url === null)
            $first_url = $url_here;
        $url_here = url($url_here);
        $current = $match_here? 'class="' . $current_css_class . '"': '';
        $output .= "<li $current><a href=\"$url_here\">$label</a>$child_tree</li>";
    }
    $output .= "</ul>";
    if ($parent_output === null)
        return $output;
    $parent_output = $output;
    return array($has_match, $first_url);
}

/**
 * Returns true if given class or object implements the given interface.
 * @param mixed $class
 * @param string $interface
 */
function implementing($class, $interface) {
    $interfaces = class_implements($class);
    return isset($interfaces[$interface]);
}

/**
 * Returns true if given class or object is abstract.
 */
function is_abstract($class) {
    static $cache = array();
    if (is_object($class)) {
        $class = get_class($class);
        if (isset($cache[$class]))
            return $cache[$class];
    } else if (!class_exists($class))
        trigger_error("Class '$class' does not exist.", \E_USER_ERROR);
    else if (isset($cache[$class]))
        return $cache[$class];
    $rc = new \ReflectionClass($class);
    return $cache[$class] = $rc->isAbstract();
}

function on_windows() {
    static $cache = null;
    if ($cache === null)
        $cache = strtoupper(substr(PHP_OS, 0, 3)) == "WIN";
    return $cache;
}

/**
 * Takes an error number and returns the constant name.
 * @example 128 returns "E_COMPILE_WARNING"
 * @see http://se2.php.net/manual/en/errorfunc.constants.php
 * @param integer $error_number
 * @return string Error constant name or NULL if no such error constant
 * or if input is a combination of constants.
 */
function get_error_name($error_number) {
    return \melt\internal\get_error_name($error_number);
}


/**
 * Works exactly like the PHP instanceof operator, however
 * it also takes a class name (string) as it's first argument.
 * @param mixed $class Class name or object to compare.
 * @param mixed $base_class The class name or object to compare with.
 * @return boolean
 * @see The PHP instanceof operator
 */
function is($class, $base_class) {
    if (is_object($class))
        return $class instanceof $base_class;
    else if (!is_string($class) || !class_exists($class))
        return false;
    if (is_object($base_class))
        $base_class = get_class($base_class);
    else if (!is_string($base_class) || !class_exists($base_class))
        return false;
    return strcasecmp($class, $base_class) === 0 || is_subclass_of($class, $base_class);
}

/**
 * Reads an uploaded file with the specified form name.
 * @param string $form_name Name of input form component.
 * @param string $file_name Returns the original remote file name.
 * @param boolean $only_path Set to true to only return uploaded file
 * location instead of loading content into memory (and returning it).
 * @return string NULL if there are no such uploaded file,
 * otherwise the contents of the uploaded file or path to uploaded
 * file (depending on $only_path).
 */
function get_uploaded_file($form_name, &$file_name = null, $only_path = false) {
    if (isset($_FILES[$form_name]) && is_uploaded_file(@$_FILES[$form_name]['tmp_name'])) {
        $file_name = @$_FILES[$form_name]['name'];
        return $only_path? $_FILES[$form_name]['tmp_name']: \file_get_contents($_FILES[$form_name]['tmp_name']);
    }
    return null;
}

/**
 * Returns an array of all modules where each key is the module name
 * and the value is an array like (module class, module file path)
 * @return array
 */
function get_all_modules() {
    return \melt\internal\get_all_modules();
}

/**
 * Takes a string numer and removes starting and ending zeros.
 * Example: "0003.1400" returns "3.14"
 * @param string $str_number
 * @return string
 */
function number_trim($str_number) {
    $str_number = \strval($str_number);
    if (\strpos($str_number, ".") !== false)
        $str_number = \rtrim(\rtrim($str_number, "0"), ".");
    $str_number = \ltrim($str_number, "0");
    if ($str_number == "" || $str_number[0] == ".")
        $str_number = "0$str_number";
    return $str_number;
}

/**
 * This function throws an E_USER_WARNING error if the application is in
 * developer mode, and proceeds to print all arguments that where
 * passed to it.
 */
function debug() {
    if (!APP_IN_DEVELOPER_MODE)
        return;
    $args = func_get_args();
    ob_start();
    if (count($args) > 1) {
        echo var_dump($args);
    } else if (count($args) == 1) {
        echo var_dump(reset($args));
    }
    $message = ob_get_contents();
    ob_end_clean();
    trigger_error("debug() triggered with the following data:\n\n$message", \E_USER_WARNING);
}

/**
 * Gets the ID for a model instance or 0 for null.
 * @param \melt\Model $instance
 * @return integer
 */
function id($instance = null) {
    if ($instance === null)
        return 0;
    else if (\is_scalar($instance))
        return intval($instance);
    else
        return $instance->getID();
}

/**
 * Sets or gets the current locale.
 * @param string $new_locale Set to non FALSE to set a new locale for the
 * next request of this session.
 * @return string The current locale if not passing argument.
 */
function current_locale($new_locale = false) {
    if ($new_locale === false)
        return LocalizationEngine::get()->getLocale();
    else
        LocalizationEngine::setNextLocale($new_locale);
}

/**
 * Inserts array2 into array1 by merging the entire array into before the
 * key $before_key. If keys are shared between array and array2 the
 * last/second appearance of the key value pair will be ignored while
 * building the new array from left to right (or top down).
 * This function takes O(n + m) time where n and m is length of array
 * and array2 respectivly.
 * @param array $array
 * @param mixed $before_key
 * @param array $array2
 * @return array
 */
function array_insert(array $array, $before_key, array $array2) {
    if (!\array_key_exists($before_key, $array))
        \trigger_error("The key '$before_key' does not exist in \$array.", \E_USER_ERROR);
    $new_array = array();
    foreach ($array as $key => $value) {
        if ($key === $before_key) {
            foreach ($array2 as $key2 => $value2) {
                if (!\array_key_exists($key2, $new_array))
                    $new_array[$key2] = $value2;
            }
            $before_key = null;
        }
        if (!\array_key_exists($key, $new_array))
            $new_array[$key] = $value;
    }
    return $new_array;
}

/**
 * Translates string.
 * @param string $msgid Must be string litteral expression. Function call is
 * parsed by localization engine.
 * @return string Locale translated string.
 */
function gettext($msgid) {
    $sprintf_args = array_slice(func_get_args(), 1);
    return LocalizationEngine::translate($msgid, "", "", 1, $sprintf_args);
}


/**
 * Translates by plural form.
 * @param string $msgid Must be string litteral expression. Function call is
 * parsed by localization engine.
 * @param string $msgid_plural
 * @param integer $n
 * @return string Locale translated string.
 */
function ngettext($msgid, $msgid_plural, $n) {
    $sprintf_args = array_slice(func_get_args(), 2);
    return LocalizationEngine::translate($msgid, $msgid_plural, "", $n, $sprintf_args);
}

/**
 * Returns a javascript function that takes "n" as a first argument and
 * then sprintf parameters.
 * @param string $msgid
 * @param string $msgid_plural
 */
function jsngettext($msgid, $msgid_plural) {
    return LocalizationEngine::translate($msgid, $msgid_plural, "", null, array());
}

/**
 * Translates by context.
 * @param string $context Context of string.
 * @param string $msgid Must be string litteral expression. Function call is
 * parsed by localization engine.
 * @return string Locale translated string.
 */
function pgettext($context, $msgid) {
    $sprintf_args = array_slice(func_get_args(), 2);
    return LocalizationEngine::translate($msgid, "", $context, 1, $sprintf_args);
}

/**
 * Returns the current date/time.
 * @return DateTime
 */
function now() {
    return new DateTime();
}

/**
 * Returns the current melt framework version.
 * @return string 
 */
function version() {
    return \melt\internal\VERSION;
}