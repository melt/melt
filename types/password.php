<?php

class PasswordType extends Type {
    public function getSQLType() {
        return "text";
    }
    public function getSQLValue() {
        return api_database::strfy($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        $value = api_html::escape($this->value);
        return "$label <input type=\"password\" name=\"$name\" value=\"$value\" />";
    }
    public function readInterface() {
        $this->value = sha1(@$_POST[$this->name] . CONFIG::$crypt_salt);
        unset($_POST[$this->name]);
    }
    public function __toString() {
        return "<i>Hidden</i>";
    }
}

?>
