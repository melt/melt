<?php

namespace nanomvc\core;

/**
 * Forks a function call, allowing parallell execution.
 * Note that forking has an extremly high overhead in terms of
 * both CPU and memory so it should be avoided unless neccessary.
 * Also note that fork only works in environments with a webserver
 * configured to handle multiple requests. In other enviroments the fork
 * will timeout after 5 seconds without throwing an exception.
 * @param callback $callback Callback function.
 * @param array $parameters Array of parameters.
 * @see call_user_func_array()
 */
function fork($callback, $parameters = array()) {
    if (!is_file(".forkkey"))
        file_put_contents(".forkkey", $forkkey = api_string::random_hex_str(16));
    else
        $forkkey = file_get_contents(".forkkey");
    // Execute fork.
    $headers = array(
        "Host" => \nanomvc\config\APP_ROOT_HOST,
        "User-Agent" => "nanoMVC/" . \nanomvc\VERSION . " (Internal Fork)",
    );
    $data = serialize(array(
        "forkkey" => $forkkey,
        "callback" => $callback,
        "parameters" => $parameters,
    ));
    $base_path = Config::$root_path;
    $status = \nanomvc\http\raw_request("http://localhost" . $base_path . "core/action/fork", "POST", $headers, $data, 15);
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
 * Returns true if the specified module exists and is at least given version.
 * @param string $module_name Module name to check for, eg "html".
 * @param string $min_version NULL for no version checkor or a minimum version eg "1.5.3"
 */
function module_loaded($module_name, $min_version = null) {
    $module_name = 'nanomvc\\' . $module_name . '\\' . \nanomvc\string\underline_to_cased($module_name) . "Module";
    if (class_exists()) {
        if ($min_version !== null) {
            $module_version = call_user_func(array($module_name, "getVersion"));
            return version_compare($module_version, $min_version, ">=");
        }
        return true;
    }
    return false;
}