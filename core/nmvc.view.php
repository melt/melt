<?php

namespace nanomvc;

/**
 * nanoView
 */
final class View {
    /** @var Controller Controller this View uses. */
    private $_controller;

    private function __construct(Controller $controller, $path) {
        $this->_controller = $controller;
        require $path;
    }

    // Syncronize view variables with their respective controller.
    function __get($name) {
        return property_exists($this->_controller, $name)? $this->_controller->$name: NullObject::getInstance();
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
     * @desc Gets a list of all the available view variables in the current
     * rendering scope. Returns an array of variable names.
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
    * @desc Renders an element in the same controller.
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
        self::render("elements/$elementPath", $controller, false);
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

    /**
    * @desc Takes a view path and finds the first valid file path to it,
     * or FALSE if no souch file exists.
    */
    private static function findView($view_path) {
        $path_cache = array();
        if (isset($path_cache[$view_path]))
            return $path_cache[$view_path];
        if ($view_path[0] != "/")
            $view_path = "/" . $view_path;
        $path = APP_DIR . "/views" . $view_path . ".php";
        if (is_file($path))
            return $path_cache[$view_path] = $path;
        // Could be a module view path. Then the first component of the path
        // is the module in which the view resides. (That way you can always
        // override views as above.
        $dir_pos = strpos($view_path, "/", 1);
        if ($dir_pos === false)
            return $path_cache[$view_path] = false;
        $module_name = substr($view_path, 1, $dir_pos - 1);
        $view_path = substr($view_path, $dir_pos);
        $path = "/modules/" . $module_name . "/views" . $view_path . ".php";
        if (is_file($full_path = APP_DIR . $path))
            return $path_cache[$view_path] = $full_path;
        else if (is_file($full_path = APP_CORE_DIR . $path))
            return $path_cache[$view_path] = $full_path;
        else
            return $path_cache[$view_path] = false;
    }


    /**
     * Renders a view (template) in an internal subrequest and returns content.
     * @param string $view_path The path of the view to show.
     * @param boolean $final_render If this should be rendered in the final
     * @param Controller $controller The controller instance that runs this view.
     * @param boolean $return Set to false to ouput buffer instead of returning it.
     * @param boolean $final Set to true to render this in the final layout.
     * If a layout path is specified in the controller with this set to true,
     * that layout path will be used to render the application layout, and
     * all section data will be consumed.
     * @param boolean $just_try Set to true to return instead of crashing if view doesn't exist.
     */
    public static function render($view_path, $controller = null, $return = true, $final = false, $just_try = false) {
        // Initialize application layout.
        static $application_layout = null;
        if ($application_layout == null)
            $application_layout = new Layout();
        // Create a dummy controller if no controller was specified.
        if ($controller === null)
            $controller = new Controller();
        if (!is_a($controller->layout, "nanomvc\Layout")) {
            // Layout not initialized yet.
            $layout_path = $controller->layout;
            if ($final)
                $controller->layout = $application_layout;
            else if ($controller->layout == '')
                $controller->layout = new VoidLayout();
            else
                $controller->layout = new Layout();
        } else
            $layout_path = '';
        $view_file_path = self::findView($view_path);
        if ($view_file_path === false) {
            if (!$just_try)
                trigger_error("nanoMVC: The view '$view_path.php' could not be found!", \E_USER_ERROR);
            else
                return;
        }
        // Buffer if returning.
        if ($return)
            ob_start();
        // Make sure there's no level imbalance.
        $level = $controller->layout->getLevel();
        // Enter content.
        if (!is_a($controller->layout, "nanomvc\\VoidLayout"))
            $controller->layout->enterSection("content");
        // Render the view.
        new View($controller, $view_file_path);
        // Exit content.
        if (!is_a($controller->layout, "nanomvc\\VoidLayout"))
            $controller->layout->exitSection();
        // Should now be back at last level.
        if ($controller->layout->getLevel() != $level)
            trigger_error("nanoMVC: After rendering '$view_path.php', a level imbalance was detected! The enterSections() does not have a balanced ammount of exitSections().", \E_USER_ERROR);
        // Will render the layout if a layout is set.
        if ($layout_path != '') {
            $controller->layout->render($layout_path);
            // If this is a render into the final "application layout",
            // that layout should now be reset.
            if ($final)
                $application_layout = null;
        }
        if ($return) {
            // Return output.
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }
    }
}

/** Buffers output to a layout. */
class Layout {
    // Section buffers.
    private $section_buffers = array();
    // The current stack of buffers.
    private $buffer_stack = array();

    public function getLevel() {
        return count($this->buffer_stack);
    }

    /**
    * @desc Displays the layout with it's buffered sections.
    */
    public function render($path) {
        if (count($this->buffer_stack) > 0)
            trigger_error("Rendering layout without exiting all sections!", \E_USER_ERROR);
        // Render layout just like a view.
        $layout_controller = new Controller();
        foreach ($this->section_buffers as $name => $section)
            $layout_controller->$name = $section->output();
        $layout_controller->layout = new VoidLayout();
        View::render($path, $layout_controller, false, false, false);
    }

    /**
    * @desc Buffer to a diffrent section in the layout.
    * @param string $name Identifier of the section. If the section name ends in _foot, it will be written in reverse chunks.
    */
    public function enterSection($name) {
        $foot_section = substr($name, -5) == '_foot';
        if (!array_key_exists($name, $this->section_buffers))
            $this->section_buffers[$name] = $section = new SectionBuffer($foot_section);
        else
            $section = $this->section_buffers[$name];
        $section->enter();
        array_push($this->buffer_stack, $section);
    }

    /**
    * @desc Exits the section in the layout.
    */
    public function exitSection() {
        $section = array_pop($this->buffer_stack);
        
        if (!is_a($section, "nanomvc\\SectionBuffer"))
              die(debug_print_backtrace());
//            trigger_error("Cannot exit section. No section to exit from!", \E_USER_ERROR);
        $section->leave();
    }

    /**
     * Inserts data directly into a section.
     * @param string $name Name of section to insert data into.
     * @param string $data Data to insert.
     */
    public function insertSection($name, $data) {
        $this->enterSection($name);
        echo $data;
        $this->exitSection();
    }
}

/** A layout without any buffer. The default layout. */
class VoidLayout extends Layout {
    private $buffer_level = 0;

    public function getLevel() {
        return $this->buffer_level;
    }

    /**
    * @desc Does nothing.
    */
    public function _finalize() {  }

    /**
    * @desc Throws away all data on this level.
    */
    public function enterSection($name) {
        $this->buffer_level += 1;
        if ($this->buffer_level == 1)
            ob_start();
    }

    /**
    * @desc Exits the section in the layout.
    */
    public function exitSection() {
        $this->buffer_level -= 1;
        if ($this->buffer_level == 0) {
            ob_clean();
            ob_end_clean();
        }
    }
}

/**
 * This is a placeholder for null references in Views.
 * It prevents exceptions from beeing thrown when accessing unset variables.
 */
class NullObject {
    // Singleton.
    private function  __construct() { }

    function __get($name) {
        return $this;
    }

    function __set($name, $value) {
        trigger_error("Setting variable on NullObject!", \E_USER_ERROR);
    }

    function __isset($name) {
        return false;
    }

    function __unset($name) {
        return;
    }

    function __toString() {
        return "";
    }

    public function __call($name,  $arguments) {
        return $this;
    }

    public static function getInstance() {
        static $instance = null;
        if ($instance == null)
            $instance = new NullObject();
        return new NullObject();
    }
}


class SectionBuffer {
    private $final_chunks = array();
    private $chunks = array();
    private $at = -1;
    private $reversed = false;

    public function __construct($reversed) {
        $this->reversed = $reversed;
    }

    public function enter() {
        $this->at++;
        if ($this->at >= 0)
            $chunks[$this->at] = "";
        ob_start();
    }

    public function leave() {
        static $calls = 0;
        $calls++;
        if ($calls > 100)
            trigger_error("wat", \E_USER_ERROR);
        $contents = ob_get_contents();
        if ($this->at > 0)
            $this->chunks[] = $contents;
        else {
            $this->final_chunks[] = $contents;
            $this->final_chunks = array_merge($this->final_chunks, $this->chunks);
            $this->chunks = array();
        }
        $this->at--;
        ob_end_clean();
    }

    public function output() {
        $output = "";
        $total = count($this->final_chunks);
        if ($this->reversed)
            for ($i = $total - 1; $i >= 0; $i--)
                $output .= $this->final_chunks[$i];
        else
            for ($i = 0; $i < $total; $i++)
                $output .= $this->final_chunks[$i];
        return $output;
    }
}