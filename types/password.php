<?php

class PasswordType extends Type {
    public function getSQLType() {
        return "text";
    }
    public function SQLize($data) {
        return api_database::strfy($data);
    }
    public function getInterface($label, $data, $name) {
        $data = api_html::escape($data);
        return "$label <input type=\"password\" name=\"$name\" value=\"$data\" />";
    }
    public function read($name, &$value) {
        $value = sha1(@$_POST[$name] . CONFIG::$crypt_salt);
        unset($_POST[$name]);
    }
    public function write($value) {
        return "<i>Hidden</i>";
    }
}

?>
