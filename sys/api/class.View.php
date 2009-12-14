<?php

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

    /**
     * @desc Generates a unique, non-random DOM ID for an object, based on the object type and the target URL.
     * @param string $object Type of object, i.e. 'form' or 'link'
     * @param string $url The object's target URL
     * @return string
     */
    function uuid($object, $url) {
        static $i = 0;
        $i++;
        // Parse Cake PHP url's.
        if (is_array($url))
            $url = '/' . implode('/', $url);
        return "i" . $i . "_" . substr(md5($object . '$' . api_string::str_represent($url), 0, 8));
    }

    /**
    * @desc Adds content to the internal scripts buffer. This buffer is made available in the layout as $scripts_for_layout. This method is helpful when creating helpers that need to add javascript or css directly to the layout. Keep in mind that scripts added from the layout, or elements in the layout will not be added to $scripts_for_layout. This method is most often used from inside helpers, like the Javascript and Html Helpers.
    * @param string $name NOT USED IN nanoMVC
    * @param string $content Content of script to add. Will insert this buffer into the <head> of the xhtml document if api_html is used as a layout.
    */
    function addScript(string $name, string $content) {
        api_html::insert_head($content);
    }
}


?>