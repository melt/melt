<?php

/**
* @desc Takes an array of arguments and travels down arrays or objects reference chains.
* @param Mixed $var Array or object to start on.
* @param Array $args Array of string references to travel trough.
*/
function _r($var, $args) {
    foreach ($args as $arg) {
        if (is_object($var))
            if (isset($var->$arg))
                $var = $var->$arg;
            else
                return null;
        else if (is_array($var))
            if (isset($var[$arg]))
                $var = $var[$arg];
            else
                return null;
        else
            return null;
        if ($var == null)
            return null;
    }
    return $var;
}

/**
* @desc Declaration enables PHP autoloading, this is a PHP "magic function".
*/
function __autoload($class_name) {
    // Do not autoload if application hasn't started.
    if (api_application::$_application_state == api_application::STATE_UNSTARTED)
        return;
    _nanomvc_autoload($class_name);
}

/**
* @desc Autloads models in controller and helpers in view according to the cake naming convention.
* @see http://book.cakephp.org/view/23/File-and-Classname-Conventions
*/
function _nanomvc_autoload($class_name) {
    // Autoloading of incorrectly named classes unsupported.
    if (strpos($class_name, "_") !== false)
        return;
    $file_name = api_string::cased_to_underline($class_name);
    // Helpers are autoloaded trough convention, otherwise try loading as a model.
    if (substr($file_name, -7) == '_helper') {
        $file_name = substr($file_name, 0, -7);
        $cls_path = "views/helpers/$file_name.php";
    } else if (substr($file_name, -5) == '_type') {
        $file_name = substr($file_name, 0, -5);
        $cls_path = "types/$file_name.php";
    } else if (substr($file_name, -10) == '_component') {
        $file_name = substr($file_name, 0, -10);
        $cls_path = "controllers/components/$file_name.php";
    } else {
        $cls_path = "models/$file_name.php";
    }
    if (!file_exists($cls_path))
        return;
    /*
    // Include core classes.
    if (!file_exists($cls_path)) {
        $cls_path = CORE_DIR . "/" . $cls_path;
        if (!file_exists($cls_path))
            return;
    }*/
    require $cls_path;
    if (!class_exists($class_name))
        throw new Exception("The class $class_name was unexpectedly NOT declared by $cls_path. Make sure that you are following the nanoMVC naming convention!");
}

/**
* @desc The application manager.
*/
class api_application {
    const STATE_UNSTARTED = 0;
    const STATE_EXECUTING = 1;
    const STATE_CONTROLLING = 2;
    const STATE_VIEWING = 3;
    /** @desc The current application states. */
    public static $_application_state = 0;
    /** @desc The application controller. Is set just before application state enters viewing. */
    public static $_application_controller = null;

    /**
    * @desc Syncs this applications model layout with the database.
    *       WARNING: Can result in major data loss.
    */
    public static function sync_layout() {
        // Need to be in developer mode to do this.
        if (!devmode)
            api_navigation::show_xyz(403);
        // Display all SQL queries made during syncronization.
        api_database::enable_display();
        // Load all models.
        $models = glob("models/*.php");
        foreach ($models as $model) {
            if (is_file($model)) {
                require_once $model;
                $cls_name_tokens = explode("_", substr(basename($model), 0, -4));
                foreach ($cls_name_tokens as &$token)
                    $token = ucfirst($token);
                $cls_name = implode($cls_name_tokens);
                if (!class_exists($cls_name) || !in_array('Model', class_parents($cls_name)))
                    throw new Exception("The model class file '$model' unexpectedly didn't declare a Model extended class with the name '$cls_name'.");
                // Syncronize this model.
                forward_static_call(array($cls_name, 'syncLayout'));
            }
        }
        exit;
    }

    /**
    * @desc Executes the given path.
    * @param String $path The full path to execute.
    */
    public static function execute($path) {
        self::$_application_state = self::STATE_EXECUTING;
        // Include default application classes.
        require "app_controller.php";
        require "app_model.php";
        // First see if any model interface data was posted.
        if (isset($_POST['_mif_header']))
            Model::processInterfaceAction();
        // Parse call tokens.
        $arguments = explode('/', $path);
        $controller = strtolower($arguments[1]);
        $action = strtolower(@$arguments[2]);
        // Reserved/special controllers.
        if ($controller == "image" || $controller == "thumbnail") {
            api_images::send_picture();
            exit;
        } else if ($controller == "dev") {
            if (!devmode)
                api_navigation::show_xyz(403);
            else if ($action == 'migrate')
                // Special path that syncronizes layout with database.
                api_application::sync_layout();
            else
                api_navigation::show_404();
            exit;
        }
        // Default controller/action = index.
        $reserved = array('dev', 'elements', 'helpers', 'layouts');
        if (!in_array($controller, $reserved)) {
            if ($controller == '')
                $controller = 'index';
            if ($action == '')
                $action = 'index';
            unset($arguments[0]);
            unset($arguments[1]);
            unset($arguments[2]);
            // If there is a controller with this name, run it.
            if (self::run($controller, $action, array_values($arguments), false))
                return;
        }
        // Pass request to standard webroot handling instead.
        self::send_static_file($path, true);
    }

    /**
    * @desc Attempts to run the specified controller action with specified arguments.
    * @param String $controller_name Name of controller to run.
    * @param String $action_name Name of action to run.
    * @param Array $arguments Arguments of controller.
    * @param Boolean $show_404_on_noaction If it should 404 if controller exists but not action.
    * @returns Boolean False if the controller did not exist, or if the controller + action did not exist and $show_404_on_noaction is true. Otherwise True.
    */
    public static function run($controller_name, $action_name, $arguments, $show_404_on_noaction = false) {
        $controller_path = "controllers/$controller_name" . "_controller.php";
        if (!file_exists($controller_path))
            return false;
        self::$_application_state = self::STATE_CONTROLLING;
        require_once $controller_path;
        {
            $clsname = explode("_", $controller_name);
            foreach ($clsname as &$part)
                $part = ucfirst($part);
            $clsname = implode("", $clsname) . "Controller";
        }
        if (!class_exists($clsname))
            throw new Exception("The controller in $controller_path did not declare the expected $clsname controller!");
        if (!method_exists($clsname, $action_name))
            if ($show_404_on_noaction)
                api_navigation::show_404();
            else
                return false;
        // Create an instance of the controller and invoke action.
        $controller = new $clsname();
        $controller->beforeFilter();
        $show = call_user_func_array(array($controller, $action_name), $arguments);
        $controller->beforeRender();
        // NULL = Display default view if it exists, FALSE = Display nothing, STRING = Force display of this view or crash, ELSE crash.
        if ($show === false)
            return;
        else if ($show === null) {
            self::show("$controller_name/$action_name", $controller, true);
        } else if (is_string($show)) {
            self::show($show, $controller);
        } else
            throw new Exception("Did not understand what controller action returned (" . var_dump($show) . ").");
        // Sending the view to the browser, but clear first.
        $controller->layout->_finalize();
        return true;
    }

    /**
    * @desc Sends a static file from the webroot.
    * @param String $path Path of file to send in webroot catalog.
    * @param Boolean $show_404 Set to true to show 404 instead of crashing when path doesn't exist.
    */
    public static function send_static_file($path, $show_404 = false) {
        $webroot_path = "webroot/$path";
        if (file_exists($webroot_path) && !is_dir($webroot_path))
            api_navigation::send_file($webroot_path, api_filesystem::resolve_mime($path));
        else if ($show_404)
            api_navigation::show_404();
        else
            throw new Exception("The static file '$path' does not exist!");
    }

    /**
    * @desc Takes a view path and finds the first valid file path to it, or FALSE if no souch file exists.
    */
    private static function findView($view_path) {
        $view_path = "views/$view_path.";
        $paths = array('', '../sys/');
        $exts = array('ctp', 'tpl', 'php');
        foreach ($paths as $path) {
            foreach ($exts as $ext) {
                $view_file_path = $path . $view_path . $ext;
                if (file_exists($view_file_path))
                    return $view_file_path;
            }
        }
        return false;
    }

    /**
    * @desc Shows a view (template).
    * @param String $view_path The path of the view to show.
    * @param Controller $controller The controller instance that runs this view.
    * @param Boolean $just_try Set to true to not crash when view can't be found.
    */
    public static function show($view_path, $controller = null, $just_try = false) {
        // Create a dummy controller if no controller was specified.
        if ($controller === null)
            $controller = new Controller();
        // Instance the layout if it has not been done yet.
        if (!is_a($controller->layout, "Layout"))
            $controller->layout = ($controller->layout != '')? new Layout($controller->layout): new VoidLayout();
        // Enter viewing state.
        self::$_application_state = self::STATE_VIEWING;
        self::$_application_controller = $controller;
        // Render the view.
        $view_file_path = self::findView($view_path);
        if ($view_file_path === false) {
            if ($just_try)
                return;
            else
                throw new Exception("The view '$view_path(tpl/php/ctp)' could not be found!");
        }
        // Pass flasher references to the controller.
        $controller->flash = Flash::getFlashRef();
        // Supporting multiple template formats.
        switch (substr($view_file_path, -3)) {
        case 'tpl':
            // Run smarty template.
            $tpl = self::compile($view_file_path);
            require_once $tpl['filename'];
            // Call wrapper function.
            if ($controller == null)
                $controller = new Object();
            call_user_func($tpl['function'], $controller);
            break;
        case 'ctp':
        case 'php':
            // Running a cake template.
            View::_runCakeView($view_file_path, $controller);
            break;
        }
    }

    /**
    * @desc Renders a view (template) and then returns the output instead of showing it (flusing it to the browser).
    * @param String $view_path The path of the view to show.
    * @param Controller $controller The controller instance that runs this view.
    * @param Boolean $just_try Set to true to not crash when view can't be found.
    */
    public static function render($view_path, $controller = null, $just_try = false) {
        ob_start();
        api_application::show($view_path, $controller, $just_try);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
    * @desc Makes sure given view is compiled.
    */
    private static function compile($view_path) {
        // Clean the view path:
        $view_path = strtolower(trim($view_path));
        $view_path = str_replace("\\", "/", $view_path);
        $view_path = preg_replace("#/{2,}#", "/", $view_path);
        // Calculate identifiers.
        $wrap_func = "tw_" . substr(md5($view_path), 0, 8);
        $wrap_dir = "cache/" . dirname($view_path);
        if (!file_exists($wrap_dir))
            mkdir($wrap_dir, 0777, true);
        $wrap_file = $wrap_dir . "/" . basename($view_path);
        $last_m = file_exists($wrap_file)? filemtime($wrap_file): false;
        if ($last_m === false || $last_m < filemtime($view_path)) {
            // Recompile required.
            $tpl_data = file_get_contents($view_path);

            self::$block = array();
            self::$special = array();
            self::$compiling_line = 1;
            self::$compiling_file = $view_path;

            // Match smarty expressions and compile.
            // Description of this match:
            // Matches (a) $variable (some echo)
            //         (b) IF whitespace, is... PHP function, <>=&| operators, smarty variables: $foo.bar, numbers.
            //         (c) SMARTY function with an var=val assignment array after.

            //                                      |<echo matching>|                    <Matching if's and else if's.>                                          | <Matching generic SMARTY syntax.>                            |    linebreaks for counting
            $tpl_data = preg_replace_callback('#{\s*(\$(\w+(\.\w+)*!?)|(elseif|if)\s+((\s*|\w+\(|,|\)|"([^\\\\"]|\\\\.)*"|[<>=&\|!]+|\d+\.?\d*|\w+|\$\w+(\.\w+)*)*)|(/?\w+)((\s*\w+=(\$\w+(\.\w+)*|[\w\.]+|"([^\\\\"]|\\\\.)*"))*))\s*}|(' . "\n" . ')#si', array('api_application', 'expr_callback'), $tpl_data);
            if (count(self::$block) > 0)
                throw new Exception(self::err_prefix() . "Error: End of file reached without closing {" . self::$block[0]['type'] . "} block!");

            /* This is a workaround to PHP eating EOL's after closing tags, which
               destroys line numbering consistency.
               http://bugs.php.net/bug.php?id=21891 */
            $tpl_data = preg_replace("#\\?>\\n#si", "?>\n\n", $tpl_data);

            // Wrap template in a function with debug globals.
            $dbg = 'global $__mod_dbg, $__mod_dbg_line, $__mod_dbg_file; $_old_mod_dbg_file = (isset($__mod_dbg_file)? $__mod_dbg_file: null); $_old_mod_dbg = (isset($__mod_dbg)? $__mod_dbg: null); $_old_mod_dbg_line = (isset($__mod_dbg_line)? $__mod_dbg_line: null);' .
                   "\$__mod_dbg = '$view_path'; \$__mod_dbg_line = 0; \$__mod_dbg_file = '$wrap_file';";
            $dbg_end = '$__mod_dbg = $_old_mod_dbg; $__mod_dbg_line = $_old_mod_dbg_line; $__mod_dbg_file = $_old_mod_dbg_file;';
            file_put_contents($wrap_file, "<?php function $wrap_func(\$__controller) { $dbg ?> $tpl_data <?php $dbg_end } ?>");
        }
        // Cached and not modified?
        return array('filename' => $wrap_file, 'function' => $wrap_func);
    }

    // Smarty compiler state.
    private static $compiling_file;
    private static $compiling_line;
    private static $block;
    private static $special;

    private static function err_prefix() {
        return "smarty compiler error [" . self::$compiling_file . " line #" . self::$compiling_line . "]: ";
    }

    /**
    *@desc Takes an expresson, finds all smarty variables and translates them.
    */
    private static function translate_smarty_vars($expr) {
        return preg_replace_callback('#"([^\\\\"]|\\\\.)*"|\$(\w+(\.\w+)*)#si', array('api_application', 'translate_smarty_var'), $expr);
    }
    private static function translate_smarty_var($match) {
        // Do not match smarty var syntax inside strings.
        if (!isset($match[2]) || $match[2] == '')
            return;
        // Translating a smarty var.
        $refs = $match[2];
        if ($refs[0] == '$')
            $refs = substr($refs, 1);
        $ref_list = explode('.', $refs);
        $first = $ref_list[0];
        unset($ref_list[0]);
        $special = isset(self::$special[$first]);
        $first = $read = ($special)? "\$_$first": "\$__controller->$first";
        if (count($ref_list) > 0) {
            // Reference walk to fetch value.
            foreach ($ref_list as &$ref)
                $ref = var_export(strval($ref), true);
            $refs = implode(',', $ref_list);
            $read = "_r($read, array($refs))";
        }
        return $special? "($read)": "(isset($first) ? $read: null)";
    }

    private static function match_injected_var($match) {
        if (isset($match[1])) {
            $php = self::translate_smarty_var(array(2 => $match[1]));
            return '" . (' . $php . ') . "';
        } else
            return '\$';
    }

    private static function parse_arg_list($args) {
        $arg_list = array();
        preg_match_all('#(\w+)=(\$\w+(\.\w+)*|[\w\.]+|"([^\\\\"]|\\\\.)*")#si', $args, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $key = $matches[1][$i];
            $value = $matches[2][$i];
            if (is_numeric($value)) {
                $value = floatval($value);
            } else if ($value[0] == '$') {
                $value = self::translate_smarty_var(array(2 => $value));
            } else if ($value[0] == '"') {
                // Find and activate smarty variables inside, also rewite all non variable dollar signs.
                $value = preg_replace_callback('#`(\$\w+(\.\w+)*)`|\$#si', array('api_application', 'match_injected_var'), $value);
            } else {
                $value = var_export($value, true);
            }
            if (strlen($value) > 0)
                $arg_list[$key] = $value;
        }
        return $arg_list;
    }

    private static function open_block($type, $special_vars) {
        foreach ($special_vars as $special) {
            if (isset(self::$special[$special]))
                throw new Exception(self::err_prefix() . "Cannot reserve a variable with name '$special' in start of '$type' block. This variable name has already been reserved by a previous block!");
            self::$special[$special] = true;
        }
        array_push(self::$block, array('type' => $type, 'special' => $special_vars));
    }

    private static function close_block($type) {
        $block = array_pop(self::$block);
        if ($block['type'] != $type) {
            $end = count(self::$block) > 0? " in a " . $block['type'] . " block!": '. No block opened.';
            throw new Exception(self::err_prefix() . "Error in template: Cannot close a '$type' block$end");
        }
        foreach ($block['special'] as $special)
            unset(self::$special[$special]);
    }

    private static function verify_block($type, $mid_block) {
        $block = array_pop(self::$block);
        if ($block['type'] != $type) {
            $end = count(self::$block) > 0? " in a " . $block['type'] . " block!": '. No block opened.';
            throw new Exception(self::err_prefix() . "Error in template: Cannot open a '$mid_block' block$end");
        }
        array_push(self::$block, $block);
    }

    private static function expr_callback($match) {
        if ($match[15] == "\n") {
            // Just a linebreak.
            self::$compiling_line += 1;
            return "\n";
        } else if ($match[2] != '') {
            // Compiling an echo.
            $var = $match[1];
            if ($do_escape = (substr($var, -1) == "!"))
                $var = substr($var, 0, -1);
            $var = self::translate_smarty_var(array(2 => $var));
            // The ! ending operator escapes before writing.
            if ($do_escape)
                $var = "api_html::escape($var)";
            $ret = "<?php echo $var; ?>";
        } else if ($match[4] != '') {
            // An [ELSE] IF expression, this is the PHP comparision code.
            $php_compare = self::translate_smarty_vars($match[5]);
            $command = $match[4];
            if ($command == 'if') {
                self::open_block('if', array());
                $ret = "<?php if ($php_compare) { ?>";
            } else {
                self::verify_block('if', 'elseif');
                $ret = "<?php } else if ($php_compare) { ?>";
            }
        } else {
            // A generic smarty expression.
            $command = $match[9];
            if ($command[0] == '/') {
                // Closing block.
                $command = substr($command, 1);
                self::close_block($command);
                switch ($command) {
                    case 'section':
                        $ret = "<?php \$__controller->layout->exitSection(); ?>";
                        break;
                    default:
                        $ret = "<?php } ?>";
                        break;
                }
            } else {
                $arguments = $match[10];
                $arg_list = self::parse_arg_list($arguments);
                switch ($command) {
                case 'if':
                    throw new Exception(self::err_prefix() . "The IF uses an unrecognized syntax.");
                    break;
                case 'else':
                    self::verify_block('if', 'else');
                    if (count($arg_list) > 0)
                        throw new Exception(self::err_prefix() . "Else does not take any arguments!");
                    $ret = "<?php } else { ?>";
                    break;
                case 'foreach':
                    if (!isset($arg_list['from']))
                        throw new Exception(self::err_prefix() . "Parameter 'from' missing in {foreach}!");
                    if (!isset($arg_list['item']))
                        throw new Exception(self::err_prefix() . "Parameter 'item' missing in {foreach}!");
                    $from = $arg_list['from'];
                    $item = $arg_list['item'];
                    $item = eval("return $item;");
                    $special = array($item);
                    if (isset($arg_list['key'])) {
                        $key = $arg_list['key'];
                        $key = eval("return $key;");
                        $special[] = $key;
                        $key_code = " \$_$key => ";
                    } else
                        $key = $key_code = null;
                    if (isset($arg_list['index'])) {
                        $index = $arg_list['key'];
                        $index = eval("return $index;");
                        $special[] = $index;
                        $index_code = " \$_$index = -1; ";
                        $index_iter = " \$_$index++; ";
                    } else
                        $index = $index_code = $index_iter = null;
                    self::open_block('foreach', $special);
                    $ret = "<?php $index_code foreach ($from as $key_code \$_$item) { $index_iter ?>";
                    break;
                case 'element':
                    // Custom smarty function: displays an element.
                    if (!isset($arg_list['path']))
                        throw new Exception(self::err_prefix() . "Parameter 'path' missing in {element}!");
                    $path = $arg_list['path'];
                    unset($arg_list['path']);
                    $pop = "";
                    foreach ($arg_list as $key => $val)
                        $pop .= "\$__controller->$key = $val;";
                    $ret = "<?php \$__element_controller = new Controller(); $pop \$__element_controller->layout = \$__controller->layout;" .
                           "api_application::show('elements/' . $path, \$__element_controller, false); ?>";
                    break;
                case 'url':
                    // Custom smarty function: creates a local url from path.
                    if (!isset($arg_list['path']))
                        $path = "REQURL";
                    else
                        $path = $arg_list['path'];
                    unset($arg_list['path']);
                    // Pass the rest of the values as query parameters.
                    $query = "";
                    foreach ($arg_list as $key => $val)
                        $query .= "'$key' => $val,";
                    $ret = "<?php echo api_html::escape(api_navigation::make_local_url($path, array($query))); ?>";
                    break;
                case 'section':
                    if (!isset($arg_list['name']))
                        throw new Exception(self::err_prefix() . "Parameter 'name' missing in {section}!");
                    $name = $arg_list['name'];
                    self::open_block('section', array());
                    $ret = "<?php \$__controller->layout->enterSection($name); ?>";
                    break;
                default:
                    throw new Exception(self::err_prefix() . "The smarty command '$command' is unrecognized/unsupported.");
                }
            }
        }
        // Count linebreaks before returning.
        self::$compiling_line += substr_count($match[0], "\n");
        return $ret;
    }

}

?>