<?php namespace nmvc;

/**
 * nanoView
 */
final class View {
    /** @var Controller Controller this View uses. */
    private $_controller;
    /** @var string Module this View uses or NULL. */
    private $_module_context = null;

    private function __construct(Controller $controller, $path, $module_context) {
        $this->_controller = $controller;
        $this->_module_context = $module_context;
        require $path;
    }

    // Transparent access to invoking controller.
    public function __call($name,  $arguments) {
        if (!method_exists($this->_controller, $name)) {
            trigger_error("The function " . get_class($this->_controller) . "->$name() does not exist!", \E_USER_WARNING);
            return null;
        }
        return call_user_func_array(array($this->_controller, $name), $arguments);
    }

    public static function __callStatic($name,  $arguments) {
        $ctrl_clsname = get_class($this->_controller);
        if (!method_exists($ctrl_clsname, $name)) {
            trigger_error("The static function $ctrl_clsname::$name() does not exist!", \E_USER_WARNING);
            return null;
        }
        return call_user_func_array(array($ctrl_clsname, $name), $arguments);
    }

    public function __set ($name,  $value) {
        $this->_controller->$name = $value;
    }

    public function __get ($name) {
        return isset($this->_controller->$name)? $this->_controller->$name: null;
    }

    public function __isset ($name) {
        return isset($this->_controller->$name);
    }

    public function __unset ($name) {
        unset($this->_controller->$name);
    }

    /* Extends a view as they are defined by cake. */

    /**
     * @desc Renders a view in the same controller.
     * @param string $view_path Path to the view to render.
     * This path can be relative to the elements folder of the current
     * module, or a FQN view path depending if it starts with "/" or not.
     * @param array $params Additional data to temporarily set when
     * rendering view.
     */
    function display($view_path, $params = array()) {
        $stack = array();
        $controller = $this->_controller;
        // Save to stack and set params.
        $layout = $controller->layout;
        foreach ($params as $key => $val) {
            $stack[$key] = isset($controller->$key)? $controller->$key: null;
            $controller->$key = $val;
        }
        if (strlen($view_path) == 0)
            trigger_error("View path not specified!", \E_USER_ERROR);
        $fqn_call = $view_path[0] == "/";
        if ($fqn_call) {
            $view = self::findView($view_path);
            if ($view === false)
                trigger_error("View path '$view_path' not found!", \E_USER_ERROR);
            // Set new module context.
            $foregin_module_context = $view[1];
            $module_context = $this->_module_context;
            $this->_module_context = $foregin_module_context;
        } else {
            $view_path = $this->_module_context . "/elements/$view_path";
        }
        self::render($view_path, $this->_controller, false, false, false);
        // Restore module context.
        if ($fqn_call)
            $this->_module_context = $module_context;
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
            $this->view_hash = "h" . string\random_alphanum_str(10);
        return $object . $this->view_hash;
    }

    /**
     * Takes a view path and finds the first valid file path to it,
     * or FALSE if no souch file exists.
     * @return array (path, module_context)
     */
    private static function findView($view_path) {
        $path_cache = array();
        if (isset($path_cache[$view_path]))
            return $path_cache[$view_path];
        if (strlen($view_path) == 0 || $view_path[0] != "/")
            $view_path = "/" . $view_path;
        $path = APP_DIR . "/views" . $view_path . ".php";
        if (is_file($path))
            return $path_cache[$view_path] = array($path, null);
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
            return $path_cache[$view_path] = array($full_path, $module_name);
        else if (is_file($full_path = APP_CORE_DIR . $path))
            return $path_cache[$view_path] = array($full_path, $module_name);
        else
            return $path_cache[$view_path] = false;
    }


    private static $application_layout = null;
    /**
     * Will reset the application layout, loosing any data that was previously
     * rendered in it.
     */
    public static function reset_app_layout() {
        self::$application_layout = null;
    }

    /**
     * Renders a view (template) in an internal subrequest and returns content.
     * @param string $view_path The path of the view to show.
     * @param boolean $final_render If this should be rendered in the final
     * @param mixed $controller The controller instance that runs this view or
     * or an array of controller data to insert into a standard controller
     * without layout, or null to use standard controller without layout
     * and no data.
     * @param boolean $return Set to false to ouput buffer instead of returning it.
     * @param boolean $final Set to true to render this in the final layout.
     * If a layout path is specified in the controller with this set to true,
     * that layout path will be used to render the application layout, and
     * all section data will be consumed.
     * @param mixed $just_try Set to false to return instead of crashing if view doesn't exist.
     */
    public static function render($view_path, $controller_data = null, $return = true, $final = false, $just_try = false) {
        // Initialize application layout.
        if (self::$application_layout == null)
            self::$application_layout = new Layout();
        // Use standard controller with empty layout
        // if no controller was specified.
        if (!is_null($controller_data) && !is_array($controller_data)
        && (!is_object($controller_data) || !is_subclass_of($controller_data, "nmvc\Controller")))
            trigger_error("Unexpected controller_data data type. Expected NULL, ARRAY or nmvc\\Controller.", \E_USER_NOTICE);
        if (!is_object($controller_data)) {
            $controller = new core\StdController();
            $controller->layout = null;
            if (is_array($controller_data)) {
                foreach ($controller_data as $key => $val)
                    $controller->$key = $val;
            }
        } else {
            $controller = $controller_data;
        }
        if ((!is_object($controller->layout) && !class_exists($controller->layout))
        || !is($controller->layout, "nmvc\Layout")) {
            // Layout not initialized yet.
            $layout_path = $controller->layout;
            if ($final)
                $controller->layout = self::$application_layout;
            else if ($controller->layout == '')
                $controller->layout = new VoidLayout();
            else
                $controller->layout = new Layout();
        } else
            $layout_path = '';
        $ret_view = self::findView($view_path);
        if ($ret_view === false) {
            if (!$just_try)
                trigger_error("nanoMVC: The view '$view_path.php' could not be found!", \E_USER_NOTICE);
            return false;
        }
        list($view_file_path, $module_context) = $ret_view;
        // Buffer if returning.
        if ($return)
            ob_start();
        // Make sure there's no level imbalance.
        $level = $controller->layout->getLevel();
        // Enter content (if not done so).
        if (!is_a($controller->layout, "nmvc\\VoidLayout") && $level == 0)
            $controller->layout->enterSection("content");
        // Render the view.
        new View($controller, $view_file_path, $module_context);
        // Exit content.
        if (!is_a($controller->layout, "nmvc\\VoidLayout") && $level == 0)
            $controller->layout->exitSection();
        // Should now be back at last level.
        if ($controller->layout->getLevel() != $level)
            trigger_error("nanoMVC: After rendering '$view_path.php', a level imbalance was detected! The enterSections() does not have a balanced ammount of exitSections().", \E_USER_ERROR);
        // Will render the layout if a layout is set.
        if ($layout_path != '') {
            $controller->layout->render($layout_path, $controller);
            // If this is a render into the final "application layout",
            // that layout should now be reset.
            if ($final)
                self::$application_layout = null;
        }
        if ($return) {
            // Return output.
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        } else
            return true;
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
    public function render($path, $layout_controller) {
        if (count($this->buffer_stack) > 0)
            trigger_error("Rendering layout without exiting all sections!", \E_USER_ERROR);
        // Render layout just like a view, but without specified layout.
        $layout_controller->layout = null;
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
        if (!is_a($section, "nmvc\\SectionBuffer"))
              trigger_error("Cannot exit section. No section to exit from!", \E_USER_ERROR);
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

/*
 * This is a placeholder for null references in Views.
 * It prevents exceptions from beeing thrown when accessing unset variables.

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
} */


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