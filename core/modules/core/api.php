<?php namespace nmvc\core;

/**
 * Forks a function call, allowing parallell execution.
 * Note that forking has an extremly high overhead in terms of
 * both CPU and memory so it should be avoided unless neccessary.
 * Also note that fork only works in environments with a webserver
 * configured to handle multiple requests. In other enviroments the fork
 * will timeout after 5 seconds without throwing an exception.
 * @param callback $callback Callback function.
 * @param array $parameters Array of parameters.
 * @see call_user_func_array
 */
function fork($callback, $parameters = array()) {
    if (!is_callable($callback))
        trigger_error("The callback '$callback' is invalid!", \E_USER_ERROR);
    if (!is_file(APP_DIR . "/.forkkey"))
        file_put_contents(APP_DIR . "/.forkkey", $forkkey = \nmvc\string\random_hex_str(16));
    else
        $forkkey = file_get_contents(".forkkey");
    // Execute fork.
    $headers = array(
        "Host" => APP_ROOT_HOST,
        "User-Agent" => "nanoMVC/" . \nmvc\internal\VERSION . " (Internal Fork)",
    );
    $data = serialize(array(
        "forkkey" => $forkkey,
        "callback" => $callback,
        "parameters" => $parameters,
    ));
    $base_path = APP_ROOT_PATH;
    if (substr($base_path, 0, -1) != "/")
        $base_path .= "/";
    $status = \nmvc\http\raw_request("http://localhost" . $base_path . "core/callback/fork", "POST", $headers, $data, 15);
    $return_code = $status[1];
    if ($return_code != "200")
        trigger_error("fork() failed! Return code: $return_code", \E_USER_ERROR);
}

/**
* @param Integer $byte Number of bytes.
* @param Boolean $si Set this to false to use IEC standard size notation instead of the SI notation. (SI: 1000 b/Kb, IEC: 1024 b/KiB)
* @return String The number of bytes in a readable unit representation.
*/
function byte_unit($byte, $si = true) {
    $byte = intval($byte);
    if ($si) {
        $u = 1000;
        $uarr = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
    } else {
        $u = 1024;
        $uarr = array("B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB");
    }
    foreach ($uarr as $unit) {
        if ($byte < $u) return strval(round($byte, 2)) . " " . $unit;
        else $byte /= $u;
    }
    return strval(round($byte, 2)) . " " . $uarr[count($uarr) - 1];
}


/**
* @desc Returns TRUE if arrays are recursivly equal, otherwise FALSE.
* @desc Also returns FALSE if any input is not of array type.
*/
function compare_arrays($array1, $array2) {
    if (!is_array($array1) || !is_array($array2) || (count($array1) != count($array2)))
        return false;
    foreach ($array1 as $key => $val) {
        if (!isset($array2[$key]))
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
 * Search the given directory for files which has a path relative to given
 * directory which matches the given regex pattern.
 * If $directory is not a directory, an empty array will be returned.
 * @param string $directory The directory to search.
 * @param integer $pattern Regex pattern to match with the relative paths
 * of the traversed files, or NULL to match all files.
 * @return array Files matching the pattern.
 */
function grep($directory, $pattern = null) {
    static $in_recurse = false;
    static $skip_charachers;
    $reset_in_recurse = false;
    if (!$in_recurse) {
        if (!is_dir($directory))
            return array();
        $directory = realpath($directory) . "/";
        $skip_charachers = strlen($directory);
        $in_recurse = $reset_in_recurse = true;
    }
    $ret = array();
    $dirhandle = opendir($directory);
    while (false !== ($nodename = readdir($dirhandle))) {
        if ($nodename[0] == ".")
            continue;
        $subpath = $directory . $nodename;
        if (is_dir($subpath))
            $ret = array_merge($ret, grep($subpath . "/", $pattern));
        else if ($pattern == null || preg_match($pattern, $subpath))
            $ret[] = substr($subpath, $skip_charachers);
    }
    closedir($dirhandle);
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
            \nmvc\request\show_404();
    }
}


/**
 * Returns true if the specified module exists and is at least given version.
 * @param string $module_name Module name to check for, eg "html".
 * @param string $min_version NULL for no version checkor or a minimum version eg "1.5.3"
 */
function module_loaded($module_name, $min_version = null) {
    $module_class_name = 'nmvc\\' . $module_name . '\\' . \nmvc\string\underline_to_cased($module_name) . "Module";
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
        foreach (array(\nmvc\internal\get_all_modules(), array('Application' => array('nmvc\AppController'))) as $module_class_names)
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
    return \nmvc\internal\get_error_name($error_number);
}


/**
 * Returns TRUE if class is a base_class.
 * Replacement for PHP is_subclass_of that refuses to return true
 * for two classes that are the same and is_a also refuses to take a
 * class name as it's first argument.
 * Methaphor for this function:
 * "Zebra", "Zebra" would return TRUE because a Zebra is a Zebra.
 * "Zebra", "Animal" would also return TRUE because a Zebra is an Animal.
 * "Zebra", "Reptile" would however return FALSE. A Zebra is not a Reptile.
 * @param mixed $class Class name or object to compare.
 * @param mixed $base_class The class name or object to compare with.
 * @return boolean
 * @see is_a(), is_subclass_of()
 */
function is($class, $base_class) {
    if (is_object($class))
        $class = get_class($class);
    else if (!is_string($class) || !class_exists($class))
        return false;
    if (is_object($base_class))
        $base_class = get_class($base_class);
    else if (!is_string($base_class) || !class_exists($base_class))
        return false;
    return strcasecmp($class, $base_class) || is_subclass_of($class, $base_class);
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

// Import some functions to the global namespace.
include __DIR__ . "/imports.php";