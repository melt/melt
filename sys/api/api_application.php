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
    } else if (substr($file_name, -11) == '_controller') {
        $cls_path = "controllers/$file_name.php";
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
    */
    private static function sync_layout() {
        // Need to be in developer mode to do this.
        if (!devmode)
            api_navigation::show_xyz(403);
        // Display all SQL queries made during syncronization.
        api_database::enable_display();
        // Refresh pointer map.
        api_database::run("DROP TABLE `" . _tblprefix . "pointer_map`");
        api_database::query("CREATE TABLE `" . _tblprefix . "pointer_map` (`child_table` varchar(64) NOT NULL, `child_column` varchar(64) NOT NULL, `parent_table` varchar(64) NOT NULL);");
        // Load all models.
        $models = glob("models/*.php");
        foreach ($models as $model) {
            if (is_file($model)) {
                require_once $model;
                $table_name = substr(basename($model), 0, -4);
                $cls_name = api_string::underline_to_cased($table_name);
                $parents = class_parents($cls_name);
                if (class_exists($cls_name) && in_array('SingletonModel', $parents))
                    continue; // Singletonmodels is not stored as tables.
                if (!class_exists($cls_name) || !in_array('Model', $parents))
                    throw new Exception("The model class file '$model' unexpectedly didn't declare a Model/SingletonModel extended class with the name '$cls_name'.");
                // Syncronize this model.
                call_user_func(array($cls_name, 'syncLayout'));
                // Record pointers for pointer map.
                $child_table = api_database::strfy($table_name);
                $columns = DataSet::getColumnNames($cls_name);
                foreach ($columns as $column) {
                    $child_column = api_database::strfy($column);
                    $pieces = array_reverse(explode("_", $column));
                    if ($pieces[1] == "id")
                        unset($pieces[1]);
                    else if ($pieces[0] != "id")
                        continue;
                    unset($pieces[0]);
                    $parent_table = api_database::strfy(implode("_", array_reverse($pieces)));
                    api_database::query("INSERT INTO `" . _tblprefix . "pointer_map` VALUES ($child_table, $child_column, $parent_table)");
                }
            }
        }
        // Initialize singleton model structure.
        SingletonModel::syncWithDatabase();
        exit;
    }

    /**
    * @desc INTERNAL FUNCTION DO NOT CALL
    * @desc Executes the application.
    */
    public static function _application_execute() {
        self::$_application_state = self::STATE_EXECUTING;
        $path = REQURL;
        // Include default application classes.
        require "app_controller.php";
        require "app_model.php";
        // Parse the request into controllers, actions and arguments.
        $parts = explode('/', $path);
        $controller = $parts[1];
        $action = @$parts[2];
        $arguments = array_slice($parts, 3);
        // Handle reserved/special controllers.
        if ($controller == "image" || $controller == "thumbnail") {
            api_images::send_picture();
            exit;
        } else if ($controller == "core") {
            if ($action == "fork")
                self::incomming_fork();
            else if (!devmode)
                api_navigation::show_xyz(403);
            else if ($action == "migrate")
                // Special path that syncronizes layout with database.
                api_application::sync_layout();
            else
                api_navigation::show_404();
            exit;
        }
        // Replace empty with index keyword. Also, the index keyword is reserved,
        // prevent visiting "index" explicitly.
        // This prevents double URL's and is good for consistancy and SEO.
        // This also means that you can't pass arguments to index, unless you use request URL rewriting.
        if ($controller == "index")
            // Redirect to / page.
            api_navigation::redirect(url("/"));
        else if (strlen($controller) == 0) {
            // This is only the / page.
            if ($path != "/")
                api_navigation::redirect(url("/"));
            $controller = "index";
            $action = "index";
        } else {
            if ($action == "index")
                api_navigation::redirect(url("/$controller"));
            else if (strlen($action) == 0) {
                // This is only the /$controller page.
                $action = "index";
                if (count($parts) > 2)
                    api_navigation::redirect(url("/$controller"));
            }
        }
        // If any arguments are empty the URL is invalid, remove them and redirect the browser.
        // This prevents double URL's and is good for consistancy and SEO.
        $clear_arg = array();
        foreach ($arguments as $arg)
            if (strlen($arg) > 0)
                $clear_arg[] = $arg;
        if (count($arguments) != count($clear_arg)) {
            $clear_arg = count($clear_arg) > 0? "/" . implode("/", $clear_arg): "";
            api_navigation::redirect(url("/$controller/$action" . $clear_arg));
            exit;
        }
        // Standard controller, rewrite the url if it should.
        AppController::rewriteRequestUrl($controller, $action, $arguments);
        // Running controllers with reserved names is not allowed.
        if (in_array($controller, array('core', 'elements', 'helpers', 'layouts')))
            api_navigation::show_404();
        // If there is a controller with this name, run it.
        if (self::run($controller, $action, $arguments, false))
            return;
        // Take the manipulated path sequance and turn it back into a path.
        if ($controller == "index")
            $path = "/";
        else if ($action == "index")
            $path = "/$controller";
        else if (count($arguments) == 0)
            $path = "/$controller/$action";
        else
            $path = "/$controller/$action/" . implode("/", $arguments);
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
    private static function run($controller_name, $action_name, $arguments, $show_404_on_noaction = false) {
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
        if (!is_subclass_of($clsname, "Controller"))
            throw new Exception("The controller named '$clsname' does not extend the class Controller as required!");
        // Create an instance of the controller and invoke action.
        $controller = new $clsname();
        $controller->beforeFilter();
        // Before calling action, interrupt request if it's a MIF post.
        Model::processInterfaceAction();
        // Call the action now.
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
    private static function send_static_file($path, $show_404 = false) {
        $webroot_path = "webroot/$path";
        if (is_file($webroot_path))
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
        $exts = array('ctp', 'php');
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
    * @desc INTERNAL FUNCTION DO NOT CALL
    * @desc Shows a view (template).
    * @param String $view_path The path of the view to show.
    * @param Controller $controller The controller instance that runs this view.
    * @param Boolean $just_try Set to true to not crash when view can't be found.
    */
    private static function show($view_path, $controller = null, $just_try = false) {
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
                throw new Exception("The view '$view_path(php/ctp)' could not be found!");
        }
        // Pass flasher references to the controller.
        $controller->flash = Flash::getFlashRef();
        // Supporting multiple template formats.
        switch (substr($view_file_path, -3)) {
        case 'ctp':
        case 'php':
            // Running a template.
            View::_run($view_file_path, $controller);
            break;
        }
    }

    /*
    * @desc Attempts to run the specified controller action with specified arguments in an internal subrequest.
    * @param String $controller_name Name of controller to run.
    * @param String $action_name Name of action to run.
    * @param Array $arguments Arguments of controller.
    */
    public static function invoke($controller_name, $action_name, $arguments) {
        // Save the application state.
        $_application_state = self::$_application_state;
        $_application_controller = self::$_application_controller;
        // Start buffer.
        ob_start();
        // Invoke.
        api_application::run($controller_name, $action_name, $arguments, false);
        // Get buffered output.
        $output = ob_get_contents();
        ob_end_clean();
        // Restore application state.
        self::$_application_state = $_application_state;
        self::$_application_controller = $_application_controller;
        return $output;
    }

    /**
    * @desc Renders a view (template) in an internal subrequest. After that, returns the output instead of showing it (flusing it to the browser).
    * @param String $view_path The path of the view to show.
    * @param Controller $controller The controller instance that runs this view.
    * @param Boolean $return Set to false to ouput buffer instead of returning it.
    */
    public static function render($view_path, $controller = null, $return = true) {
        // Save the application state.
        $_application_state = self::$_application_state;
        $_application_controller = self::$_application_controller;
        // Buffer if returning.
        if ($return)
            ob_start();
        // Render.
        api_application::show($view_path, $controller, false);
        // Restore application state.
        self::$_application_state = $_application_state;
        self::$_application_controller = $_application_controller;
        if ($return) {
            // Return output.
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }
    }


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
    public static function fork($callback, $parameters = array()) {
        if (!is_file(".forkkey"))
            file_put_contents(".forkkey", $forkkey = api_string::random_hex_str(16));
        else
            $forkkey = file_get_contents(".forkkey");
        // Execute fork.
        $headers = array(
            "Host" => Config::$root_host,
            "User-Agent" => "nanoMVC/" . nmvc_version . " (Internal Fork)",
        );
        $data = serialize(array(
            "forkkey" => $forkkey,
            "callback" => $callback,
            "parameters" => $parameters,
        ));
        $base_path = Config::$root_path;
        $status = api_http::raw_request("http://localhost" . $base_path . "core/fork", "POST", $headers, $data, 15);
        $return_code = $status[1];
        if ($return_code != "200")
            throw new Exception("fork() failed! Return code: $return_code");
    }
    
    private static function incomming_fork() {
        /* Only accepts incomming forks if request is trusted.
           Definition of trusted request: Localhost that can read our fork key.
           Technically, this would allow a localhost on a shared server
           that can read but not write each others directories to inject
           function calls.
           This is however not a security breach as read permission would allow
           you to read other sensitive data anyway, like passwords.
           Read permission therefore indicates a sufficient level of trust.*/
        if (gethostbyaddr($_SERVER['REMOTE_ADDR']) != "localhost")
            api_navigation::show_xyz(403);
        $data = unserialize(file_get_contents("php://input"));
        if (!is_array($data))
            api_navigation::show_xyz(403);
        if (!file_exists(".forkkey") || file_get_contents(".forkkey") != $data['forkkey'])
            api_navigation::show_xyz(403);
        // Fork accepted, unhook from the current request to prevent
        // the parent from waiting for this request to finish, allowing
        // parallell execution.
        api_http::unhook_current_request();
        // Commence execution.
        $callback = $data['callback'];
        $parameters = $data['parameters'];
        call_user_func_array($callback, $parameters);
        exit;
    }
}

?>