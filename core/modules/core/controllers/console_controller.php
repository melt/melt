<?php namespace melt\core;

class ConsoleController extends InternalController {
    public $layout = "/html/html5";

    public function beforeFilter($action_name, $arguments) {
        parent::beforeRender($action_name, $arguments);
        if ($action_name !== "index" && !APP_IN_DEVELOPER_MODE)
            \melt\request\show_xyz(403);
    }

    public function index() {
        \melt\js\JsModule::beforeRequestProcess();
        return "/core/console";
    }

    /*
    public function get_tab_tree() {
        \melt\request\send_json_data(array(
            "sync" => true,
            "repair" => true,
            "purify" => true,
            "cull" => true,
        ));
    }*/

    private function beginExec() {
        // Enter streaming pipe mode.
        \ignore_user_abort(false);
        \set_time_limit(0);
        \melt\request\reset();
        \header('Content-Type: text/plain; charset=UTF-8');
        \ob_end_clean();
        \ob_end_clean();
        \ob_start('ob_gzhandler');
        \ob_implicit_flush(true);
        \error_reporting(\E_USER_ERROR | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR);
        \ob_flush();
        \flush();
    }

    public function check_login() {
        \melt\request\send_json_data(config\CONSOLE_MOTD);
    }

    public function config_get_all() {
        $all_configs = array();
        foreach (\melt\internal\get_all_modules() as $module_name => $module_parameters) {
            $module_path = $module_parameters[1];
            $mod_cfg_path = $module_path . "/config.php";
            if (\is_file($mod_cfg_path)) {
                $config_directives = require($mod_cfg_path);
                assert(\is_array($config_directives));
                foreach ($config_directives as $config_var_name => $default_value)
                    $all_configs[$module_name][\strtolower($config_var_name)] = true;
            }
        }
        \melt\request\send_json_data($all_configs);
    }

    public function cmd_config($module_name, $config_var_name = null) {
        $this->beginExec();
        $modules = \melt\internal\get_all_modules();
        if (!isset($modules[$module_name]))
            die("No such module.\n");
        $config_directives = array();
        $mod_cfg_path = $modules[$module_name][1] . "/config.php";
        if (\is_file($mod_cfg_path)) {
            $config_directives = require($mod_cfg_path);
            assert(\is_array($config_directives));
        }
        $print_directive = function($directive, $default) use ($module_name) {
            $config_fqdn = "melt\\$module_name\\config\\$directive";
            echo "$config_fqdn:\n";
            echo \var_export(\constant($config_fqdn), true) . " (" . \var_export($default, true) . ")\n\n";
        };
        if ($config_var_name === null) {
            foreach ($config_directives as $directive => $default)
                $print_directive($directive, $default);
        } else {
            $config_var_name = \strtoupper($config_var_name);
            if (!\array_key_exists($config_var_name, $config_directives))
                die("No such directive.\n");
            if (!isset($_GET["set"])) {
                $print_directive($config_var_name, $config_directives[$config_var_name]);
            } else {
                $new_value = $_GET["set"];
                $local = @$_GET["local"] === "true";
                if (\strcasecmp($new_value, "true") === 0)
                    $new_value = true;
                else if (\strcasecmp($new_value, "false") === 0)
                    $new_value = false;
                else if (\strcasecmp($new_value, "null") === 0)
                    $new_value = null;
                else if (\is_numeric($new_value))
                    $new_value = (float) $new_value;
                else
                    $new_value = (string) $new_value;
                \melt\internal\put_configuration_directive("melt\\$module_name\\config\\$config_var_name", $new_value, true, $local);
            }
        }
        exit;
    }

    private function fetchMethodCode(\ReflectionMethod $reflection) {
        $file = new \SplFileObject($reflection->getFileName());
        $file->seek($reflection->getStartLine() - 1);
        $code = "";
        while ($file->key() < $reflection->getEndLine()) {
            $code .= $file->current();
            $file->next();
        }
        return $code;
    }
    
    public function cmd_obj($type) {
        $this->beginExec();
        $types = array(
            "classes" => null,
            "actions" => null,
            "controllers" => 'melt\Controller',
            "models" => 'melt\Model',
            "views" => 'melt\View',
            "types" => 'melt\Type',
        );
        if (!\array_key_exists($type, $types))
            die("Unknown object: $type\n");
        $class = $types[$type];
        $obj = @$_GET['obj'];
        $app_only = @$_GET['app'] === "true";
        $cat = @$_GET['cat'] === "true";
        if (isset($_GET['make'])) {
            $name = $_GET['make'];
            if ($type === "actions")
                die("Cannot make an action from command line. Edit the respective controller instead.\n");
            if ($type === "views") {
                if (!\preg_match('#^(/[a-z0-9_]+)+$#', $name))
                    die("Invalid view path.\n");
                $view_tokens = explode("/", substr($name, 1));
                chdir(APP_DIR . "/views");
                foreach ($view_tokens as $i => $view_token) {
                    if ($i === count($view_tokens) - 1) {
                        if (!copy(APP_DIR . "/core/scaffolding/views/generic.php", $view_token . ".php"))
                            die("Failed to copy generic.php from scaffolding to target directory.\n");
                        break;
                    } else if (!is_dir($view_token)) {
                        if (!mkdir($view_token))
                            die("Could not create directory \"$view_token\" in \"" . getcwd() . "\".\n");
                    }
                    chdir($view_token); 
                }
                die("View was successfully created at $name.php\n");
            } else {
                if (!\preg_match('/([a-z]+[a-z0-9]*)(_[a-z]+[a-z0-9]*)*/', $name))
                    die("Invalid name. For example, supply \"object_name\" to create the class ObjectName.\n");
                $suffix = $class !== null? "_" . substr($type, 0, -1): "";
                $file_name = "$name$suffix.php";
                $class_name = \melt\string\underline_to_cased($name);
                $file_data = file_get_contents(APP_DIR . "/core/scaffolding/$type/generic.php");
                if ($file_data === false)
                    die("Failed to read generic.php from scaffolding.\n");
                $file_data = str_replace("__template_class_name", $class_name, $file_data);
                $out_path = APP_DIR . "/$type/$file_name";
                if (is_file($out_path))
                    die("Object at $out_path already exists!\n");
                if (file_put_contents($out_path, $file_data) === false)
                    die("Failed to write $out_path\n");
                die(($suffix !== ""? ucfirst(substr($type, 0, -1)): "Class") . " was successfully created at /$type/$file_name\n");
            }
        }        
        $identifier_is_acceptable_fn = function($identifier) use ($app_only) {
            return !$app_only || (\strpos($identifier, "__") !== false && \strpos($identifier, "/") !== false);
        };
        if ($obj === null) {
            if ($cat)
                die("You need to specify exactly one file to display.\n");
            if ($type === "views") {
                $method = "getAll" . \ucfirst($type);
                foreach ($class::$method() as $view_path => $file_path) {
                    if (!$app_only
                    || (!\melt\string\starts_with($file_path, "/core")
                    && !\melt\string\starts_with($file_path, "/modules")))
                        echo "$view_path\n";
                }
            } else if ($type === "actions") {
                foreach (\melt\Controller::getAllControllers() as $identifier => $controller) {
                    if (!$identifier_is_acceptable_fn($identifier))
                        continue;
                    foreach ($controller::getActions() as $action)
                        echo $controller::getPath($action) . "\n";
                }
            } else {
                if ($class !== null) {
                    $method = "getAll" . \ucfirst($type);
                    $classes = $class::$method();
                } else {
                    $classes = \melt\internal\get_all_classes("", "classes", true, "/");
                    $classes = \array_merge($classes, array(
                        'melt\Model',
                        'melt\AppModel',
                        'melt\Controller',
                        'melt\AppController',
                        'melt\Type',
                        'melt\AppType',
                        'melt\View',
                    ));
                }
                foreach ($classes as $identifier => $obj) {
                    if ($identifier_is_acceptable_fn($identifier))
                        echo "$obj\n";
                }
            }
            exit;
        } else {
            $print_file_data_fn = function($file) {
                $data = \file_get_contents($file);
                $lines = substr_count($data, "\n");
                echo "$lines lines, " . BytesType::byteUnit(\strlen($data), true, true, 2) . "\n";
            };
            switch ($type) {
            case "actions":
                $action = $obj;
                $action_invoke_data = \melt\Controller::pathToInvokeData($action, true, true);
                if ($action_invoke_data === false)
                    die("Unknown action specified.\n");
                $controller = $action_invoke_data->getControllerClass();
                $action_name = $action_invoke_data->getActionName();
                $reflector = new \ReflectionMethod($controller, $action_name);
                if ($cat)
                    die($this->fetchMethodCode($reflector) . "\n");
                $file_path = $reflector->getFileName();
                $action_path = \preg_replace('#/index$#', '', $action);
                if ($action_path == "")
                    $action_path = "/";
                $is_index_path = ($action !== $action_path);
                $action_test_path = $action_path;
                if (!$is_index_path) {
                    foreach ($reflector->getParameters() as $parameter) {
                        assert($parameter instanceof \ReflectionParameter);
                        if ($parameter->isOptional()) {
                            $action_path .= "[";
                        } else {
                            $action_test_path .= "/0";
                        }
                        $action_path .= "/\$" . $parameter->getName(); 
                    }
                    $action_path .= \str_repeat("]", $reflector->getNumberOfParameters() - $reflector->getNumberOfRequiredParameters());
                }
                echo "action path: $action_path\n";
                echo "controller: $controller\n";
                echo "file path: " . \substr($file_path, \strlen(APP_DIR)) . " "
                . $reflector->getStartLine() . "-" . $reflector->getEndLine() . "\n";
                $internal = $action_name[0] === "_";
                echo "visibility: " . ($internal? "internal": "external") . "\n";
                echo "rechability: ";
                if ($internal) {
                    echo "Internal actions can not be externally invoked.\n";
                } else {
                    $rewrite_result = \melt\AppController::rewriteRequest(\explode("/", \substr($action_test_path, 1)));
                    if ($rewrite_result === null) {
                        echo "Externally reachable. AppController::rewriteRequest() does not rewrite \"$action_test_path\".\n";
                        echo "url: " . url($action_test_path) . "\n";
                    } else {
                        echo "Unknown. AppController::rewriteRequest() rewrites \"$action_test_path\" to:";
                        echo ($rewrite_result === false? " 404": \var_export($rewrite_result, true)) . "\n";
                    }
                }                
                break;
            case "classes":
            case "controllers":
            case "models":
            case "types":
                if (!\class_exists($obj) || ($class !== null && !is($obj, $class)))
                    die("Class $obj not found among $type.\n");
                $reflector = new \ReflectionClass($obj);
                $file_path = $reflector->getFileName();
                if ($cat)
                    die(\file_get_contents($file_path));
                echo "file path: " . \substr($file_path, \strlen(APP_DIR)) . "\n";
                $print_file_data_fn($file_path);
                $doc_comment = $reflector->getDocComment();
                echo "\n";
                if ($doc_comment === false) {
                    echo "Class is non-documented.\n";
                } else {
                    $doc_parser = new DocCommentParser($doc_comment);
                    if ($doc_parser->isInternal())
                        echo "INTERNAL: This class is internal. You should not use it or rely on it.\n";
                    if ($doc_parser->isDeprecated())
                        echo "DEPRECATED: This class is deprecated. You should not use or rely on it.\n";
                    $author = $doc_parser->getAuthor();
                    if ($author != null)
                        echo "Author: $author\n";
                    $desc = $doc_parser->getDescription();
                    if ($desc != null)
                        echo "Description:\n$desc\n";
                }
                break;
            case "views":
                $all_views = \melt\View::getAllViews();
                if (!isset($all_views[$obj]))
                    die("View path $obj not found among views.");
                if ($cat)
                    die(\file_get_contents(APP_DIR . $all_views[$obj]));
                echo "view path: $obj\n";
                echo "file path: $all_views[$obj]\n";
                $print_file_data_fn(APP_DIR . $all_views[$obj]);
                break;
            }
        }
        exit;
    }

    public function cmd_info() {
        \phpinfo();
        exit;
    }

    public function cmd_session_restart() {
        SessionDataModel::select()->unlink();
        die("\n>>> Application restarted, all sessions where teared down!\n\n");
    }

    public function cmd_locale($action = null, $locale = null) {
        if ($action !== "export")
            $this->beginExec();
        if ($locale !== null && \strlen($locale) !== 2 && $action !== "import")
            die("Error: Locale code must be a two letter code.\n");
        $engine = LocalizationEngine::get();
        switch ($action) {
        case null:
            $locales = $engine->getLocales();
            echo "There are currently " . \count($locales) . " installed locales:\n";
            echo \implode(", ", $locales) . "\n";
            break;
        case "create":
            $engine->createLocale($locale);
            echo "The locale \"$locale\" was created successfully.\n";
            break;
        case "remove":
            $engine->removeLocale($locale);
            echo "The locale \"$locale\" was removed successfully.\n";
            break;
        case "export":
            $po_content = $engine->exportLanguage($locale);
            \melt\request\reset();
            \header('Content-Disposition: attachment; filename=melt-translation-' . $locale . '.po');
            \header('Content-Type: text/plain');
            echo $po_content;
            exit;
        case "import":
            $po_content = get_uploaded_file("po_file", $file_name, false);
            if (!\preg_match('#^melt-translation-([a-z][a-z])#', $file_name, $matches))
                die("Error: The file name you're uploading must begin with 'melt-translation-xx' where xx is the locale you are importing.\n");
            $locale = $matches[1];
            $engine->importLanguage($po_content, $locale);
            echo "The locale \"$locale\" was imported successfully.\n";
            break;
        case "switch":
            $engine->setNextLocale($locale);
            echo "The locale \"$locale\" was switched successfully.\n";
            break;
        default:
            \melt\request\show_404();
        }
        exit;
    }

    public function cmd_sync() {
        $this->beginExec();
        \melt\db\enable_display();
        \melt\Model::syncronizeAllModels();
        die("\n\n>>> Database syncronization complete!\n\n");
    }

    public function cmd_repair() {
        $this->beginExec();
        \melt\db\enable_display();
        \melt\Model::repairAllModels();
        die("\n\n>>> Model repairation complete!\n\n");
    }

    public function cmd_purify() {
        $this->beginExec();
        \melt\db\enable_display();
        \melt\Model::purifyAllModels();
        die("\n\n>>> Model purification complete!\n\n");
    }

    public function cmd_cull() {
        $this->beginExec();
        \melt\db\enable_display();
        \melt\Model::cullAllModels();
        die("\n\n>>> Model culling complete!\n\n");
    }

    public function cmd_xkcd() {
        $this->beginExec();
        // This parser was written by Chuck Norris.
        \preg_match('#<img src="http://imgs.xkcd.com/comics/[^>]+>#', \file_get_contents("http://xkcd.org/"), $matches);
        die(@$matches[0]);
    }
}
