<?php

namespace nanomvc\core;

class PasswordType extends \nanomvc\Type {
    public function getSQLType() {
        return "text";
    }

    public function getSQLValue() {
        return api_database::strfy($this->value);
    }

    public function getInterface($name, $label) {
        $value = api_html::escape($this->value);
        return "$label <input type=\"password\" name=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = sha1(@$_POST[$name] . CONFIG::$crypt_salt);
        unset($_POST[$this->name]);
    }
    
    public function view() {
        return "<i>Hidden</i>";
    }
}


