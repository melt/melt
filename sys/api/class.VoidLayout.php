<?php

/**
* @desc A layout without any buffer.
*/
class VoidLayout extends Layout {
    private $buffer_level = 0;

    /**
    * @desc Overrides parent.
    */
    public function VoidLayout() { }

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


?>