<?php

namespace nmvc\core;

class TextType extends \nmvc\AppType {
    public function getSQLType() {
        return "text";
    }
    
    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        $value = escape($this->value);
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = @$_POST[$name];
    }

    public function view() {
        return escape(strval($this->value));
    }
}
