<?php

namespace nanomvc\core;

class TextType extends \nanomvc\Type {
    public function getSQLType() {
        return "text";
    }
    
    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name, $label) {
        $value = escape($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = @$_POST[$name];
    }

    public function view() {
        return escape(strval($this->value));
    }
}
