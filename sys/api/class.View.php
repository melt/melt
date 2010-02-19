<?php

/**
* @desc This class is used when rendering views.
*       It allows transparent access to the view controller variables,
*       and non transparent standard cake access trough getVar and set.
*/
class View {
    private $_controller;

    private function __construct($path, $parent_controller) {
        $this->_controller = $parent_controller;
        require $path;
    }

    /**
    *@desc Do not invoke manually.
    */
    public static function _runCakeView($path, $parent_controller) {
        $cakeView = new View($path, $parent_controller);
    }


    /* Syncronize the views variables with the controllers. */
    function __get($name) {
        return isset($this->_controller->$name)? $this->_controller->$name: NullObject::getInstance();
    }

    function __set($name, $value) {
        $this->_controller->$name = $value;
    }

    function __isset($name) {
        return isset($this->_controller->$name);
    }

    function __unset($name) {
        unset($this->_controller->$name);
    }

    /* Extends a view as they are defined by cake. */

    /**
    * @desc Views have a set() method that is analogous to the set() found in Controller objects. It allows you to add variables to the viewVars. Using set() from your view file will add the variables to the layout and elements that will be rendered later.
    * @param string $var The var to set.
    * @param mixed $value The value to write.
    * @see http://book.cakephp.org/view/821/set
    */
    function set($var, $value) {
        $this->_controller->$var = $value;
    }

    /**
    * @desc Gets the value of the viewVar with the name $var.
    * @param string $var The var to get.
    * @see http://book.cakephp.org/view/822/getVar
    * @return The value of that variable or NULL if no such variable exists.
    */
    function getVar($var) {
        return @$this->_controller->$var;
    }

    /**
    * @desc Gets a list of all the available view variables in the current rendering scope. Returns an array of variable names.
    * @see http://book.cakephp.org/view/823/getVars
    */
    function getVars() {
        return get_object_vars($this->controller);
    }

    /**
    * @desc Displays an error page to the user.
    *       DOES NOT USE layouts/error.ctp to render the page, and DOES stop the code execution.
    * @param integer $code HTTP status code.
    * @param string $name Name of the error. (null to show default)
    * @param string $message Error message. (null to show default)
    */
    function error($code, $name = null, $message = null) {
        api_navigation::show_xyz($code, $name, $message);
    }

    /**
    * @desc Renders an element.
    * @param string $elementPath Path to the element to render.
    * @param array $params Additional data to pass to the element controller namespace.
    */
    function element($elementPath, $params = array()) {
        $controller = api_application::$_application_controller;
        $stack = array();
        // Save to stack and set params.
        foreach ($params as $key => $val) {
            $stack[$key] = isset($controller->$key)? $controller->$key: null;
            $controller->$key = $val;
        }
        api_application::render("elements/$elementPath", $controller, false);
        // Restore from stack.
        foreach ($stack as $key => $val)
            $controller->$key = $val;
    }

    /**
    * @desc Generates a unique non-random DOM ID for an object, based on the object name.
    *       DOES NOT USE url. The hash it returns will differ per view.
    * @param String $object Object name.
    */
    private $view_hash = null;
    function uuid($object) {
        if ($this->view_hash == null)
            $this->view_hash = "h" . api_string::random_hex_str(10);
        return $object . $this->view_hash;
    }
}


?>