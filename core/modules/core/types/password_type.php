<?php namespace melt\core;

class PasswordType extends TextType {
    public function getInterface($name) {
        $value = escape($this->value);
        return "<input type=\"password\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = $_POST[$name];
    }
    
    public function __toString() {
        return "<i>Hidden</i>";
    }
}


