<?php

namespace nanomvc;

/**
 * nanoController
 */
class Controller {
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
     */
    public function beforeFilter() {}

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

    
    /**
     * Attempts to find the controller specified by the given path.
     * @param mixed $path Invoke path. Either an array of path tokens or
     * otherwise an unsplit string path.
     * @param boolean $standard_invoke When set to true
     * controllers, actions or modules with a starting "_" is ignored.
     * @returns mixed FALSE if path not found, otherwise array(controller_class_name, action_name, arguments)
     */
    public static function pathToController($path, $standard_invoke = false) {
        if ($path[0] == "/")
            $path = substr($path, 1);
        $controller_name = "nanomvc";
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
            else if ($standard_invoke && $controller_name[0] == "_")
                return false;
            $controller_name = ucfirst(string\underline_to_cased($controller_name));
            if ($i == 0)
                $controller_class_name = "nanomvc\\" . $controller_name . "Controller";
            else
                $controller_class_name = "nanomvc\\" . $path_parts[0] . "\\" . $controller_name . "Controller";
            $action_name = strtolower(@$path_parts[$i + 1]);
            if (strlen($action_name) == 0)
                $action_name = "index";
            else if ($action_name == "index") // "index" is reserved.
                return false;
            else if ($standard_invoke && $action_name[0] == "_")
                return false;
            // Check if controller exists (actually loads it if it does).
            if (class_exists($controller_class_name))
                break;
            if ($i == 1)
                // Class not found.
                return false;
        }
        return array($controller_class_name, $controller_name, $action_name, array_slice($path, $i + 2));
    }


    /**
     * Attempts to run the specified controller by given path.
     * nanoMVC mapping determines if controller exists.
     * @param mixed $path Path to invoke. Either an array of path tokens or
     * otherwise an unsplit string path.
     * @param boolean $standard_invoke When set to true behaviour will change:
     * - controllers, actions or modules with a starting "_" is ignored,
     * - the default XHTML layout is used for non set layouts.
     * - passing incorrect number of argument will safely return false.
     * @returns boolean FALSE if path not found, otherwise TRUE.
     */
    public static function invoke($path, $standard_invoke = false) {
        if ($path[0] == "/")
            $path = substr($path, 1);
        $path_parts = is_array($path)? $path: explode("/", $path);
        $controller_param = self::pathToController($path_parts, $standard_invoke);
        if ($controller_param === false)
            return false;
        // Class found.
        list($controller_class_name, $controller_name, $action_name, $arguments) = $controller_param;
        if (!method_exists($controller_class_name, $action_name))
            return false;
        // Create an instance of the controller and invoke action.
        $controller = new $controller_class_name();
        static $first_invoke = true;
        if ($first_invoke || $standard_invoke) {
            // Enable programmers to leave out the layout specifyer for
            // controllers that are invoked the standard way.
            // This should increese productivity and result in less confusion
            // when returning blank pages for new controllers, as PHP
            // programmers are used to the behaviour where PHP initializes
            // all parameters required to output HTML by default (e.g. doing
            // header("Content-Type: text/html") by itself.)
            if (!isset($controller->layout) || $controller->layout == "")
                $controller->layout = '/html/xhtml1.1';
            $first_invoke = false;
        }
        $controller->beforeFilter();
        if ($standard_invoke) {
            $method_reflector = new \ReflectionMethod($controller_class_name, $action_name);
            $total_req_parameters = $method_reflector->getNumberOfRequiredParameters();
            if (count($arguments) < $total_req_parameters)
                return false;
            $max_parameters = $method_reflector->getNumberOfParameters();
            if (count($arguments) > $max_parameters)
                return false;
        }
        // Call the action now.
        $ret_view = call_user_func_array(array($controller, $action_name), $arguments);
        $controller->beforeRender();
        // NULL = Display default view if it exists,
        // FALSE = Display nothing,
        // STRING = Force display of this view or crash,
        // ELSE crash.
        if ($ret_view === false)
            return true;
        else if ($ret_view === null) {
            if (strtolower($controller_name) == "index") {
                $path_parts[] = "index";
                $path_parts[] = "index";
            } else if (strtolower($controller_name) == "index")
                $path_parts[] = "index";
            if (count($arguments) > 0)
                $path_parts = array_slice($path_parts, 0, -count($arguments));
            $found_view = View::render(implode("/", $path_parts), $controller, false, true, true);
        } else if (is_string($ret_view))
            $found_view = View::render($ret_view, $controller, false, true, true);
        else
            trigger_error("Did not understand what controller action returned (" . var_dump($ret_view) . ").", \E_USER_ERROR);
        // Rendering complete.
        $controller->afterRender();
        return true;
    }
}


