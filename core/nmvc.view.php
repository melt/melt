<?php namespace nmvc;

final class View {
    /** @var Controller Controller this View uses. */
    private $_controller;
    /** @var string Module this View uses or NULL. */
    private $_module_context = null;

    private function __construct(Controller $controller, $path, $module_context) {
        $this->_controller = $controller;
        $this->_module_context = $module_context;
        if (\nmvc\core\config\MAINTENANCE_MODE)
            internal\check_require_prefix($path, $module_context);
        require $path;
    }

    // Transparent access to invoking controller.
    public function __call($name,  $arguments) {
        if (!method_exists($this->_controller, $name)) {
            // Check if name is a variable that contains a closure.
            if (isset($this->_controller->$name)) {
                $value = $this->_controller->$name;
                if ($value instanceof \Closure) {
                    return call_user_func_array($value, $arguments);
                }
            }
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

    public function __set($name,  $value) {
        if (strtolower($name) == "layout")
            trigger_error("May not overwrite layout!", \E_USER_ERROR);
        $this->_controller->$name = $value;
    }

    public function __get($name) {
        // Firstly try return a controller variable.
        if (isset($this->_controller->$name))
            return $this->_controller->$name;
        // Secondly, try return a layout section.
        return $this->_controller->layout->readSection($name);
    }

    public function __isset($name) {
        return isset($this->_controller->$name);
    }

    public function __unset($name) {
        unset($this->_controller->$name);
    }


    /**
     * Renders a view in the same context (controller) as the current render.
     * If you specify a relative view path (relative to the current module
     * elements path) the module context will also be preserved.
     * However, you can also specify an absolute path, in which case
     * module context is lost.
     * @param string $view_path Path to the view to render.
     * This path is relative to the elements folder of the current
     * module, if it doesn't start with a "/".
     * @param array $data Additional data to temporarily set when
     * rendering view.
     * @param boolean $return Set to true to return render result instead.
     * @return string
     */
    function display($view_path, $data = array(), $return = false) {
        $stack = array();
        $controller = $this->_controller;
        // Save to stack and set params.
        foreach ($data as $key => $val) {
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
        $ret = self::render($view_path, $this->_controller, $return, false, false);
        // Restore module context.
        if ($fqn_call)
            $this->_module_context = $module_context;
        // Restore from stack.
        foreach ($stack as $key => $val)
            $controller->$key = $val;
        if ($return)
            return $ret;
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
     * @return void
     */
    public static function reset_app_layout() {
        self::$application_layout = null;
    }

    /**
     * Renders a view (template) in the specified controller.
     * The layout of the controller is used when rendering.
     * While rendering, the controllers layout will be replaced with a
     * Layout object and rendered, except if it is already replaced.
     * Also, the layout will not be rendered if no layout path is specified.
     * In that case, data written in the top level section is lost.
     * After the Layout object is created (replaced), the rendering will take
     * place in the "content" section by default.
     * @param string $view_path The path of the view to show.
     * @param mixed $controller_data The controller instance that runs this
     * view and contains the layout to render in.
     * @param boolean $return Set to false to output the return value directly
     * to output buffer rather than returning it by value.
     * @param boolean $final Setting this to true without passing a layout
     * will use the application layout instead when rendering.
     * @param mixed $just_try Set to false to return instead of crashing if
     * view doesn't exist.
     * @return boolean Returns FALSE if the view wasn't found and $just_try
     * is set to TRUE. Otherwise returns the output or NULL if returning
     * output in output buffer instead (when $return is FALSE).
     * NULL can also be returned on nonexisting output.
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
        // Get the view file path and module context.
        $ret_view = self::findView($view_path);
        if ($ret_view === false) {
            if (!$just_try)
                trigger_error("nanoMVC: The view '$view_path.php' could not be found!", \E_USER_NOTICE);
            return false;
        }
        list($view_file_path, $module_context) = $ret_view;
        // Make sure layout is initialized.
        $layout_path = null;
        if (!($controller->layout instanceof Layout)) {
            $layout_path = $controller->layout;
            if ($final)
                $controller->layout = self::$application_layout;
            else
                $controller->layout = new Layout();
        }
        // Buffer the view output that it should return or ignore if
        // render layout is true.
        ob_start();
        $level = $controller->layout->getLevel();
        // Only setting "content" level by default in layout specified top renders.
        $top_render = $level == 0 && $layout_path !== null;
        if ($top_render)
            $controller->layout->enterSection("content");
        new View($controller, $view_file_path, $module_context);
        if ($top_render)
            $controller->layout->exitSection();
        // Should now be back at last level.
        if ($controller->layout->getLevel() != $level)
            trigger_error("nanoMVC: After rendering '$view_path.php', a level imbalance was detected! The enterSections() does not have a balanced ammount of exitSections().", \E_USER_ERROR);
        // Should render if it prepared the layout and returned to root level.
        $content = null;
        if ($layout_path != null && $controller->layout->getLevel() == 0) {
            // Throw away any output that was ignored.
            ob_end_clean();
            $content = $controller->layout->render($layout_path, $controller);
            // Reset layout now when it has been rendered.
            $controller->layout = $layout_path;
        } else {
            // Throw away default level content if outputting by echo and
            // this render is not a sub render.
            if ($return || $controller->layout->getLevel() > 0)
                $content = \ob_get_contents();
            ob_end_clean();
            // Restore the non-set layout.
            if ($final)
                $controller->layout = null;
        }
        // There are two ways to return content.
        if ($return) {
            return $content;
        } else {
            if ($content != null)
                echo $content;
            return true;
        }
    }
}

/** Buffers output to a layout. */
class Layout {
    const LAYOUT_RENDER = "\x00";

    // Section buffers.
    private $section_buffers = array();
    // The current stack of buffers.
    private $buffer_stack = array();

    public function getLevel() {
        return count($this->buffer_stack);
    }

    /** Displays the layout with it's buffered sections. */
    public function render($path, $layout_controller) {
        if (count($this->buffer_stack) > 0)
            trigger_error("Rendering layout without exiting all sections! (Bottom of stack: " . $this->buffer_stack[0]->getName() . ")", \E_USER_ERROR);
        // If just a layout render, it should return content.
        if ($path == self::LAYOUT_RENDER)
            return $this->readSection("content");
        // Render layout just like a view, but without specified layout.
        foreach ($this->section_buffers as $name => $section)
            $layout_controller->$name = $this->readSection($name);
        $layout_controller->layout = self::LAYOUT_RENDER;
        return View::render($path, $layout_controller, true, false, false);
    }

    /**
     * Buffer to a diffrent section in the layout.
     * The section names *_head and *_foot are two special sections every
     * section has that allows writing before and after section content.
     * If the section name ends in _foot, it will also be written in
     * reverse chunks to prevent double XML wrapping to be malformed.
     * @param string $name Identifier of the section.
     */
    public function enterSection($name) {
        if (!array_key_exists($name, $this->section_buffers)) {
            $foot_section = substr($name, -5) == '_foot';
            $this->section_buffers[$name] = $section = new SectionBuffer($name, $foot_section);
        } else
            $section = $this->section_buffers[$name];
        $section->enter();
        array_push($this->buffer_stack, $section);
    }

    /**
     * Exits the section in the layout.
     */
    public function exitSection() {
        $section = array_pop($this->buffer_stack);
        if (!is_a($section, "nmvc\\SectionBuffer"))
              trigger_error("Cannot exit section. No section to exit from!", \E_USER_ERROR);
        $section->leave();
    }

    /**
     * Compiles and returns the content that has been entered into a section
     * so far - including any headers and footers wrapping it.
     * If requesting a header or footer (_head/_foot) only that header or
     * footer will be returned.
     * Will return NULL for unknown/empty sections.
     * @param string $name Name of section to return.
     * @return string
     */
    public function readSection($name) {
        $end = substr($name, -5);
        if ($end == "_head" || $end == "_foot")
            $parts = array($name);
        else
            $parts = array($name . "_head", $name, $name . "_foot");
        $content = null;
        foreach ($parts as $part_name)
        if (array_key_exists($part_name, $this->section_buffers))
            $content .= $this->section_buffers[$part_name]->output();
        return $content;
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

class SectionBuffer {
    private $name;
    private $final_chunks = array();
    private $chunks = array();
    private $at = -1;
    private $reversed = false;
    private $cache = null;

    public function __construct($name, $reversed) {
        $this->reversed = $reversed;
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function enter() {
        $this->at++;
        if ($this->at >= 0)
            $chunks[$this->at] = "";
        ob_start();
    }

    public function leave() {
        $this->cache = null;
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
        if ($this->cache !== null)
            return $this->cache;
        $output = "";
        $total = count($this->final_chunks);
        if ($this->reversed)
            for ($i = $total - 1; $i >= 0; $i--)
                $output .= $this->final_chunks[$i];
        else
            for ($i = 0; $i < $total; $i++)
                $output .= $this->final_chunks[$i];
        $this->cache = $output;
        return $output;
    }
}