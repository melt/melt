<?php

namespace nmvc\core;

class PasswordType extends \nmvc\Type {
    public function getSQLType() {
        return "text";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        $value = api_html::escape($this->value);
        return "<input type=\"password\" name=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = $_POST[$name];
    }
    
    public function view() {
        return "<i>Hidden</i>";
    }
}


