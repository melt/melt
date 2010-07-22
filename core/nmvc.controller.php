<?php namespace nmvc;

/**
 * nanoController
 */
abstract class Controller {
    /**
     * @var mixed The name of the layout to render the view inside of.
     * The name specified is the filename of the layout in /app/layouts
     * without the php extension.
     * This is instanced to a Layout when rendering.
     */
    public $layout = null;

    /**
     * This function is executed before every action in the controller.
     * It's a handy place to check for an active session or
     * inspect user permissions.
     * @param $action_name Action name about to be called. This function may
     * be called with additional parameters.
     */
    public function beforeFilter($action_name) {}

    /**
     * Called after controller action logic, but before the view is rendered.
     */
    public function beforeRender() {}

    /**
     * Called after every controller action, and after rendering is complete.
     * This is the last controller method to run.
     */
    public function afterRender() {}

    /*
     * Override this function in application controller to rewrite urls.
     * @param array $path_tokens An array of path tokens to manipulate.
     *
    public static function rewriteRequestUrl(&$path_tokens) {}*/


    /** Generates a path to this controller action and parameters. */
    public static function getPath($action = null, $parameters = array()) {
        return self::controllerToPath(get_called_class(), $action, $parameters);
    }

    /**
     * Generates a path from a controller class name, action and parameters array.
     */
    public static function controllerToPath($controller_class_name, $action = null, $parameters = array()) {
        $controller = string\cased_to_underline($controller_class_name);
        $controller = substr($controller, 5, -11);
        if ($action == "index")
            $action = null;
        $path = "/";
        if ($controller != "index") {
            $path .= $controller;
            if ($action !== null) {
                $path .= "/" . $action;
                foreach ($parameters as $parameter)
                    $path .= "/" . $parameter;
            }
        }
        return $path;
    }
    
    /**
     * Attempts to find the controller specified by the given path.
     * @param mixed $path Invoke path. Either an array of path tokens or
     * otherwise an unsplit string path.
     * @param boolean $ignore_internal_declarations When set to true
     * controllers, actions or modules with a starting "_" is ignored.
     * @returns mixed FALSE if path not found, otherwise array(controller_class_name, controller_name, action_name, arguments)
     */
    public static function pathToController($path, $ignore_internal_declarations = false) {
        if ($path[0] == "/")
            $path = substr($path, 1);
        $path_parts = is_array($path)? $path: explode("/", $path);
        // Try to load application controller first, and if
        // that doesn't exist, try to load module controller.
        for ($i = 0; ; $i++) {
            // Determine controller + action. Empty names maps to "index".
            $controller_name = strtolower(@$path_parts[$i]);
            if (strlen($controller_name) == 0)
                $controller_name = "index";
            else if ($controller_name == "index") // "index" is reserved.
                return false;
            else if ($ignore_internal_declarations && $controller_name[0] == "_")
                return false;
            $controller_name = ucfirst(string\underline_to_cased($controller_name));
            if ($i == 0)
                $controller_class_name = "nmvc\\" . $controller_name . "Controller";
            else
                $controller_class_name = "nmvc\\" . $path_parts[0] . "\\" . $controller_name . "Controller";
            $action_name = strtolower(@$path_parts[$i + 1]);
            if (strlen($action_name) == 0)
                $action_name = "index";
            else if ($action_name == "index") // "index" is reserved.
                return false;
            else if ($ignore_internal_declarations && $action_name[0] == "_")
                return false;
            // Check if controller exists (actually loads it if it does).
            if (class_exists($controller_class_name))
                break;
            if ($i == 1)
                // Class not found.
                return false;
        }
        return array($controller_class_name, $controller_name, $action_name, array_slice($path_parts, $i + 2));
    }

    /**
     * Attempts to run the specified controller by given path.
     * This function also takes any number of extra arguments that will be
     * passed to the controller action.
     * @param mixed $path Path to invoke. Either an array of path tokens or
     * otherwise an unsplit string path.
     * @param boolean $use_controller_layout Set to true to render in the controller
     * specifyed layout.
     */
    public static function invoke($path, $use_controller_layout = false) {
        $extra_arguments = func_num_args() > 2? array_slice(func_get_args(), 2): array();
        $ret = self::invokeInternal($path, $extra_arguments, false, !$use_controller_layout);
        if ($ret === false)
            trigger_error("Could not invoke '$path'. Not found!", \E_USER_WARNING);
    }

    /**
     * Used once by nanoMVC to invoke from the external request.
     * This invoke has less privligies, why the separate function.
     * You can override this function in the AppController if you want to
     * rewrite the request somehow.
     * @param array $path_tokens Array of path tokens (/token1/token2/...)
     */
    public static function invokeFromExternalRequest($path_tokens) {
        return self::invokeInternal($path_tokens, array(), true, false);
    }

    private static $invoke_stack = array();

    /**
     * Returns the controller that are currently beeing invoked, or NULL
     * if no controller is currently beeing invoked.
     * @return Controller
     */
    public static function getCurrentlyInvoked() {
        $ret = end(self::$invoke_stack);
        if ($ret === false)
            $ret = null;
        return $ret;
    }

    private static function invokeInternal($path, $extra_arguments, $ignore_internal_declarations, $ignore_controller_layout) {
        if ($path[0] == "/")
            $path = substr($path, 1);
        $path_parts = is_array($path)? $path: explode("/", $path);
        $controller_param = self::pathToController($path_parts, $ignore_internal_declarations);
        if ($controller_param === false)
            return false;
        // Class found.
        list($controller_class_name, $controller_name, $action_name, $arguments) = $controller_param;
        if (!method_exists($controller_class_name, $action_name))
            return false;
        // Create an instance of the controller and invoke action.
        $controller = new $controller_class_name();
        // The code below is depricated by the all controllers extends AppController system.
        /*static $first_invoke = true;
        if ($first_invoke || $standard_invoke) {
            $first_invoke = false;
            // Enable programmers to leave out the layout specifyer for
            // controllers that are invoked the standard way.
            // This should increese productivity and result in less confusion
            // when returning blank pages for new controllers, as PHP
            // programmers are used to the behaviour where PHP initializes
            // all parameters required to output HTML by default (e.g. doing
            // header("Content-Type: text/html") by itself.)
            if (!isset($controller->layout) || $controller->layout == "")
                $controller->layout = '/html/xhtml1.1';
        }*/
        $arguments = array_merge($arguments, $extra_arguments);
        // Make sure right number of arguments are passed.
        $method_reflector = new \ReflectionMethod($controller_class_name, $action_name);
        $total_req_parameters = $method_reflector->getNumberOfRequiredParameters();
        if (count($arguments) < $total_req_parameters)
            return false;
        $max_parameters = $method_reflector->getNumberOfParameters();
        if (count($arguments) > $max_parameters)
            return false;
        // Before filter.
        call_user_func_array(array($controller, "beforeFilter"), array_merge(array($action_name), $arguments));
        // Call the action now.
        $ret_view = call_user_func_array(array($controller, $action_name), $arguments);
        // Put this invoke on stack.
        array_push(self::$invoke_stack, $controller);
        // Invoke before render callbacks.
        static $first_render = true;
        if ($first_render) {
            $first_render = false;
            foreach (internal\get_all_modules() as $module_parameters) {
                $module_clsname = $module_parameters[0];
                $module_clsname::beforeRender();
            }
        }
        $controller->beforeRender();
        if ($ignore_controller_layout)
            $controller->layout = null;
        // NULL = Display default view if it exists,
        // FALSE = Display nothing,
        // STRING = Force display of this view or crash,
        // ELSE crash.
        if ($ret_view === false) {
            array_pop(self::$invoke_stack);
            return true;
        } else if ($ret_view === null) {
            if (strtolower($controller_name) == "index") {
                $path_parts[] = "index";
                $path_parts[] = "index";
            } else if (strtolower($action_name) == "index") {
                $path_parts[] = "index";
            }
            $path_part_cnt = -count($arguments) + count($extra_arguments);
            if ($path_part_cnt < 0)
                $path_parts = array_slice($path_parts, 0, $path_part_cnt);
            $found_view = View::render(implode("/", $path_parts), $controller, false, true, true);
        } else if (is_string($ret_view)) {
            $found_view = View::render($ret_view, $controller, false, true, true);
        } else
            trigger_error("Did not understand what controller action returned (" . var_dump($ret_view) . ").", \E_USER_ERROR);
        // Rendering complete.
        $controller->afterRender();
        array_pop(self::$invoke_stack);
        return true;
    }
}


