<?php

namespace nmvc\internal;

/**
 * Returns an array of all availible modules.
 * Mapped MODULE_NAME => array(MODULE_CLASSNAME, MODULE_PATH)
 */
function get_all_modules() {
    static $modules = null;
    // Return result if cached.
    if ($modules !== null)
        return $modules;
    // Scan modules
    $modules = array();
    foreach (scandir(APP_DIR . "/modules") as $module)
        if ($module[0] != ".")
            $modules[$module] = array("nmvc\\$module\\" . underline_to_cased($module) . "Module", APP_DIR . "/modules/" . $module);
    foreach (scandir(APP_CORE_DIR . "/modules") as $module)
        if ($module[0] != ".")
            $modules[$module] = array("nmvc\\$module\\" . underline_to_cased($module) . "Module", APP_CORE_DIR . "/modules/" . $module);
    return $modules;
}

/**
* Converts class names to identifyers.
*/
function cased_to_underline($text) {
    return strtolower(preg_replace('#([a-z0-9])([A-Z])#', '\1_\2', $text));
}

/**
* Converts identifyers to class names.
*/
function underline_to_cased($text) {
    $tokens = explode("_", $text);
    foreach ($tokens as &$token)
        $token = ucfirst($token);
    return implode($tokens);
}

/**
 * The nanoMVC internal autoload function.
 * Its function are determined by the naming rules of
 * nanoMVC applications/modules.
 */
function autoload($name) {
    $parts = explode("\\", $name);
    if ($parts[0] != "nanomvc")
        return false;
    $part_cnt = count($parts);
    if ($part_cnt == 1 || $part_cnt > 3)
        // Not nanoMVC.
        return false;
    else if (($part_cnt >= 2 && $parts[1] == "") || ($part_cnt == 3 && $parts[2] == ""))
        // Invalid.
        return false;
    // When finding a class it has too look in a maximum of 8 places.
    for ($i = 0; $i < 3; $i++) {
        if ($i == 0) {
            // Application level.
            if ($part_cnt == 3)
                continue;
            $path = APP_DIR;
            $subdir = "";
            $file_name = $parts[1];
            $class_name = "nmvc\\" . $parts[1];
        } else if ($i == 1) {
            // Application level module override.
            if ($part_cnt == 2)
                return false;
            $path = APP_DIR;
            $subdir = $parts[1] . "/";
            $file_name = $parts[2];
            $class_name = "nmvc\\" . $parts[1] . "\\" . $parts[2];
        } else if ($i == 2) {
            // Module.
            $modules = get_all_modules();
            $module_name = $parts[1];
            if (!isset($modules[$module_name]))
                return false;
            $path = $modules[$module_name][1];
            $subdir = "";
            $file_name = $parts[2];
            $class_name = "nmvc\\" . $parts[1] . "\\" . $parts[2];
        }
        $file_name = \nmvc\string\cased_to_underline($file_name);
        // Using nanoMVC naming rules to find the class.
        if (\nmvc\string\ends_with($class_name, "Controller")) {
            $path .= "/controllers/" . $subdir . substr($file_name, 0, -11) . "_controller.php";
            $expecting = 'nmvc\AppController';
        } else if (\nmvc\string\ends_with($class_name, "Type")) {
            $path .= "/types/" . $subdir . substr($file_name, 0, -5) . "_type.php";
            $expecting = 'nmvc\AppType';
        } else if (\nmvc\string\ends_with($class_name, "Model")) {
            $path .= "/models/" . $subdir . substr($file_name, 0, -6) . "_model.php";
            $expecting = 'nmvc\AppModel';
        } else if (\nmvc\string\ends_with($class_name, "Module")) {
            if ($i < 2)
                continue;
            $path .= "/" . $subdir . "module.php";
            $expecting = 'nmvc\Module';
        } else {
            $path .= "/classes/" . $subdir . $file_name . ".php";
            $expecting = null;
        }
        if (!is_file($path))
            continue;
        require $path;
        if (!class_exists($class_name) && !interface_exists($class_name))
            trigger_error("nanoMVC: '$path' did not declare a class named '$class_name' as expected!", \E_USER_ERROR);
        else if ($expecting !== null && !is_subclass_of($class_name, $expecting))
            trigger_error("nanoMVC: '$class_name' must extend '$expecting'! (Declared in '$path')", \E_USER_ERROR);
        return true;
    }
}

// Registers autoload function.
spl_autoload_register("nmvc\internal\autoload");

// Include the API's of all modules.
foreach (get_all_modules() as $module_name => $module_parameters) {
    $module_path = $module_parameters[1];
    $api_path = $module_path . "/api.php";
    if (is_file($api_path))
        require $api_path;
}

// Include application specifyers.
foreach(array(
array('\nmvc\AppController', '\nmvc\Controller', 'app_controller.php'),
array('\nmvc\AppModel', '\nmvc\Model', 'app_model.php'),
array('\nmvc\AppType', '\nmvc\Type', 'app_type.php')) as $app_include) {
    list($class, $base, $file) = $app_include;
    require APP_DIR . "/" . $file;
    if (!class_exists($class) || !is_subclass_of($class, $base))
        trigger_error("$class must be declared in $file and extend $base! (Application design restriction)", \E_USER_ERROR);
    else if (!\nmvc\core\is_abstract($class))
        trigger_error("$class must be declared abstract! (Application design restriction)", \E_USER_ERROR);
}