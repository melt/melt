<?php

class FloatType extends Type {
    public function getSQLType() {
        return "float";
    }
    public function SQLize($data) {
        return floatval($data);
    }
    public function getInterface($label, $data, $name) {
        $data = floatval($data);
        return "$label <input type=\"text\" name=\"$name\" value=\"$data\" />";
    }
    public function read($name, &$value) {
        $value = floatval(@$_POST[$name]);
    }
    public function write($value) {
        return strval(floatval($value));
    }
}

?>
