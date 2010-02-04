<?php

/**
* @desc Buffers output to a layout.
*/
class Layout {
    // Sections, additional sections to buffer to.
    private $sections = array('content' => '');
    // Pointer to the current buffer.
    private $at = null;
    private $at_stack = array();
    // If writing chunks in reverse order.
    private $reverse = false;
    private $reverse_stack = array();

    private $path;

    /**
    * @desc Constructs this layout.
    * @param string $path Path to layout template.
    */
    public function Layout($path) {
        $this->path = $path;
        $this->at = 'content';
        api_misc::ob_reset();
        ob_start();
    }

    /**
    * @desc Displays the layout with it's buffered sections.
    */
    public function _finalize() {
        $this->flushSection();
        ob_end_clean();
        // Render layout just like a view.
        $layout_controller = new Controller();
        foreach ($this->sections as $name => $content)
            $layout_controller->$name = $content;
        $layout_controller->layout = new VoidLayout();
        api_application::render("layouts/" . $this->path, $layout_controller, false);
        // Unset my buffers.
        unset($this->content);
        unset($this->sections);
    }

    private function flushSection() {
        if ($this->reverse)
            $this->sections[$this->at] = ob_get_contents() . $this->sections[$this->at];
        else
            $this->sections[$this->at] .= ob_get_contents();
        ob_clean();
    }

    /**
    * @desc Buffer to a diffrent section in the layout.
    * @param string $name Identifier of the section. If the section name ends in _foot, it will be written in reverse chunks.
    */
    public function enterSection($name) {
        $this->flushSection();
        array_push($this->at_stack, $this->at);
        array_push($this->reverse_stack, $this->reverse);

        if (!array_key_exists($name, $this->sections))
            $this->sections[$name] = "";
        $foot_section = substr($name, -5) == '_foot';

        $this->at = $name;
        $this->reverse = $foot_section;
    }

    /**
    * @desc Exits the section in the layout.
    */
    public function exitSection() {
        $this->flushSection();
        $this->at = array_pop($this->at_stack);
        $this->reverse = array_pop($this->reverse_stack);
    }

    /**
    * @desc Sets the content of a section.
    */
    public function setSection($name, $value) {
        $this->sections[$name] = $value;
    }
}


?>