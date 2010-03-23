<?php

/**
* @desc Buffers output to a layout.
*/
class Layout {
    // Section buffers.
    private $section_buffers = array();
    // The current stack of buffers.
    private $buffer_stack = array();

    private $path;

    /**
    * @desc Constructs this layout.
    * @param string $path Path to layout template.
    */
    public function Layout($path) {
        $this->path = $path;
        $this->enterSection("content");
    }

    /**
    * @desc Displays the layout with it's buffered sections.
    */
    public function _finalize() {
        if (count($this->buffer_stack) > 1)
            throw new Exception("Finalizing layout without exiting all sections!");
        $this->exitSection();
        // Render layout just like a view.
        $layout_controller = new Controller();
        foreach ($this->section_buffers as $name => $section)
            $layout_controller->$name = $section->output();
        $layout_controller->layout = new VoidLayout();
        api_application::render("layouts/" . $this->path, $layout_controller, false);
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


?>