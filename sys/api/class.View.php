<?php

/**
* @desc This class is only used when rendering cake views.
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
        return isset($this->_controller->$name)? $this->_controller->$name: null;
    }

    function __set($name, $value) {
        $this->_controller->$name = $value;
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
    * @desc Renders an element or view partial.
    * @param string $elementPath Path to the element to render.
    * @param array $params Data to pass to the element.
    * @param boolean $loadHelpers NOT USED IN nanoMVC
    */
    function element($elementPath, $params = array(), $loadHelpers = false) {
        $dummy_controller = new Controller();
        foreach ($params as $key => $val)
            $dummy_controller->$key = $val;
        // Elements inherit the layout.
        $dummy_controller->layout = api_application::$_application_controller->layout;
        api_application::show("elements/$elementPath", $dummy_controller);
    }
}


?>