<?php namespace nmvc\internal;

/**
 * Returns an array of all availible modules.
 * Mapped MODULE_NAME => array(MODULE_CLASSNAME, MODULE_PATH)
 */
function get_all_modules() {
    static $modules = null;
    // Return result if cached.
    if ($modules !== null)
        return $modules;
    // Scan modules.
    foreach (modules_using() as $module) {
        if (!is_dir(APP_DIR . "/modules/" . $module))
            trigger_error("The module '$module' specified in config.php is not installed!", \E_USER_ERROR);
        $modules[$module] = array("nmvc\\$module\\" . underline_to_cased($module) . "Module", APP_DIR . "/modules/" . $module);
    }
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


function create_blank_override_class($path, $class, $extends) {
    $pos = strrpos($class, '\\');
    if ($pos === false)
        development_crash("dboc_missing_namespace", array("path" => $path, "class_name" => $class));
    $namespace = substr($class, 0, $pos);
    $class = substr($class, $pos + 1);
    $pos = strrpos($extends, '\\');
    if ($pos === false)
        development_crash("dboc_missing_namespace", array("path" => $path, "class_name" => $extends));
    $extends = substr($extends, $pos + 1);
    $file_data = "<?php namespace $namespace;\n/* Auto generated empty class override. */\n\n\nclass $class extends $extends {\n\t\n}\n";
    if (!is_dir(dirname($path)))
        mkdir(dirname($path), 0775, true);
    if (file_exists($path))
        development_crash("dboc_filesystem_conflict", array("path" => $path));
    file_put_contents($path, $file_data);
}

/**
 * The nanoMVC internal autoload function.
 * Its function are determined by the naming rules of
 * nanoMVC applications/modules.
 */
function autoload($name) {
    $parts = explode("\\", $name);
    if ($parts[0] != "nmvc")
        return false;
    $part_cnt = count($parts);
    if ($part_cnt == 1 || $part_cnt > 3)
        // Not nanoMVC.
        return false;
    else if (($part_cnt >= 2 && $parts[1] == "") || ($part_cnt == 3 && $parts[2] == ""))
        // Invalid.
        return false;
    $pending_app_override = false;
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
            $app_overridable_declarable = false;
        } else if ($i == 1) {
            // Module.
            if ($part_cnt == 2)
                return false;
            $modules = get_all_modules();
            $module_name = $parts[1];
            if (!isset($modules[$module_name]))
                return false;
            $path = $modules[$module_name][1];
            $subdir = "";
            $file_name = $parts[2];
            $class_name = "nmvc\\" . $parts[1] . "\\" . $parts[2];
            $app_overridable_declarable = true;
        } else if ($i == 2) {
            // Application level module extention.
            $path = APP_DIR;
            $subdir = $parts[1] . "/";
            $file_name = $parts[2];
            $class_name = "nmvc\\" . $parts[1] . "\\" . $parts[2];
            $app_overridable_declarable = false;
        }
        $file_name = \nmvc\string\cased_to_underline($file_name);
        // Using nanoMVC naming rules to find the class.
        if (\nmvc\string\ends_with($class_name, "Controller")) {
            $path .= "/controllers/" . $subdir . substr($file_name, 0, -11) . "_controller.php";
            $must_extend = 'nmvc\AppController';
        } else if (\nmvc\string\ends_with($class_name, "Type")) {
            $path .= "/types/" . $subdir . substr($file_name, 0, -5) . "_type.php";
            $must_extend = 'nmvc\AppType';
        } else if (\nmvc\string\ends_with($class_name, "Model")) {
            $path .= "/models/" . $subdir . substr($file_name, 0, -6) . "_model.php";
            $must_extend = 'nmvc\AppModel';
        } else if (\nmvc\string\ends_with($class_name, "Module")) {
            if ($i == 2)
                return false;
            $path .= "/" . $subdir . "module.php";
            $must_extend = 'nmvc\Module';
            $app_overridable_declarable = false;
        } else {
            $path .= "/classes/" . $subdir . $file_name . ".php";
            $must_extend = null;
        }
        if (!is_file($path)) {
            if (!$pending_app_override)
                continue;
            // Did not find application override, create a blank one.
            create_blank_override_class($path, $class_name, $class_ao_name);
        }
        if ($pending_app_override)
            // This is an application override, must extend the _app_overrideable declared class.
            $must_extend = $class_ao_name;
        require $path;
        if (!class_exists($class_name) && !interface_exists($class_name)) {
            $trigger_error = true;
            if ($app_overridable_declarable) {
                // Might be declared _app_overrideable.
                $class_ao_name = $class_name . "_app_overrideable";
                if (class_exists($class_ao_name) || interface_exists($class_ao_name)) {
                    if (!\nmvc\core\is_abstract($class_ao_name))
                        development_crash("ao_must_be_abstract", array("path" => $path, "class_name" => $class_ao_name));
                    // For further validation.
                    $class_name = $class_ao_name;
                    $pending_app_override = true;
                    $trigger_error = false;
                }
            }
            if ($trigger_error)
                development_crash("invalid_class_name", array("path" => $path, "expected_name" => $class_name));
        }
        if (\nmvc\core\config\MAINTENANCE_MODE) {
            // Also check case sensitivity.
            if (!in_array($class_name, get_declared_classes()) && !in_array($class_name, get_declared_interfaces()))
                development_crash("invalid_class_name", array("path" => $path, "expected_name" => $class_name));
            // Also check file initializor.
            $namespace = preg_replace('#\\\\[^\\\\]*$#', "", $class_name);
            $prefix = "<?php namespace $namespace;";
            $h = fopen($path, "r");
            $found = fread($h, strlen($prefix));
            if ($found != $prefix)
                development_crash("invalid_prefix", array("path" => $path, "prefix" => $prefix, "found" => $found));
            fclose($h);

        }
        if ($must_extend !== null && !is_subclass_of($class_name, $must_extend))
            development_crash("invalid_parent_class", array("path" => $path,  "class_name" => $class_name, "must_extend" => $must_extend));
        if ($pending_app_override)
            continue;
        return true;
    }
}

// Registers autoload function.
spl_autoload_register("nmvc\internal\autoload");

// Include the API's of all modules and configure them.
foreach (get_all_modules() as $module_name => $module_parameters) {
    $module_path = $module_parameters[1];
    $api_path = $module_path . "/api.php";
    if (is_file($api_path))
        require $api_path;
    // Configure module.
    $mod_cfg_path = $module_path . "/config.php";
    if (is_file($mod_cfg_path)) {
        $config_directives = require($mod_cfg_path);
        if (!is_array($config_directives))
            trigger_error("The file '$mod_cfg_path' did not return an array as expected!");
        foreach ($config_directives as $config_var_name => $default_value) {
            $config_var_fqn = "nmvc\\$module_name\\config\\$config_var_name";
            put_configuration_directive($config_var_fqn, $default_value);
        }
    }
}

// Include application specifyers.
foreach(array(
array('\nmvc\AppController', '\nmvc\Controller', 'app_controller.php'),
array('\nmvc\AppModel', '\nmvc\Model', 'app_model.php'),
array('\nmvc\AppType', '\nmvc\Type', 'app_type.php')) as $app_include) {
    list($class, $base, $file) = $app_include;
    require APP_DIR . "/" . $file;
    if (!class_exists($class))
         development_crash("invalid_class_name", array("path" => $path, "expected_name" => $class));
    else if (!is_subclass_of($class, $base))
         development_crash("invalid_parent_class", array("path" => $path,  "class_name" => $class, "must_extend" => $base));
    else if (!\nmvc\core\is_abstract($class))
         development_crash("must_be_abstract", array("path" => $path,  "class_name" => $class));
}
