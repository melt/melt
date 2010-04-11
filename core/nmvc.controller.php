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

    /**
     * Override this function in application controller to rewrite urls.
     * @param array $path_tokens An array of path tokens to manipulate.
     */
    public static function rewriteRequestUrl(&$path_tokens) {}

    /**
     * Attempts to run the specified controller by given path.
     * nanoMVC mapping determines if controller exists.
     * @param mixed $path Path to invoke. Either an array of path tokens or
     * otherwise an unsplit string path.
     * @returns boolean False if path not found, otherwise true.
     */
    public static function invoke($path) {
        $controller_name = "nanomvc";
        $path_parts = is_array($path)? $path: explode("/", $path);
        // Try to load application controller first, and if
        // that doesn't exist, try to load module controller.
        for ($i = 0; ; $i++) {
            // Determine controller + action. Empty names maps to "index".
            $part = strtolower(@$path_parts[$i]);
            if (strlen($part) == 0)
                $part = "index";
            else if ($part == "index") // "index" is reserved.
                return false;
            $part = ucfirst(string\underline_to_cased($part));
            if ($i == 0)
                $controller_class_name = "nanomvc\\" . $part . "Controller";
            else
                $controller_class_name = "nanomvc\\" . $path_parts[0] . "\\" . $part . "Controller";
            $action_name = strtolower(@$path_parts[$i + 1]);
            if (strlen($action_name) == 0)
                $action_name = "index";
            else if ($action_name == "index") // "index" is reserved.
                return false;
            // Check if controller exists (actually loads it if it does).
            if (class_exists($controller_class_name))
                break;
            if ($i == 1)
                // Class not found.
                return false;
        }
        // Class found.
        if (!method_exists($controller_class_name, $action_name))
            return false;
        // Create an instance of the controller and invoke action.
        $controller = new $controller_class_name();
        $controller->beforeFilter();
        // Call the action now.
        $arguments = array_slice($path, $i + 2);
        $ret_view = call_user_func_array(array($controller, $action_name), $arguments);
        $controller->beforeRender();
        // NULL = Display default view if it exists,
        // FALSE = Display nothing,
        // STRING = Force display of this view or crash,
        // ELSE crash.
        if ($ret_view === false)
            return true;
        else if ($ret_view === null)
            View::render(implode("/", $path_parts), $controller, false, true, true);
        else if (is_string($ret_view))
            View::render($ret_view, $controller, false, true, true);
        else
            trigger_error("Did not understand what controller action returned (" . var_dump($ret_view) . ").", \E_USER_ERROR);
        // Rendering complete.
        $controller->afterRender();
        return true;
    }
}


