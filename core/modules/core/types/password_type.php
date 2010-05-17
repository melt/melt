<?php

namespace nmvc\core;

class PasswordType extends \nmvc\AppType {
    public function getSQLType() {
        return "text";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        $value = api_html::escape($this->value);
        return "<input type=\"password\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = $_POST[$name];
    }
    
    public function __toString() {
        return "<i>Hidden</i>";
    }
}


