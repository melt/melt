<?php

class IntegerType extends Type {
    public function getSQLType() {
        return "int";
    }
    public function SQLize($data) {
        return intval($data);
    }
    public function getInterface($label, $data, $name) {
        $data = intval($data);
        return "$label <input type=\"text\" name=\"$name\" value=\"$data\" />";
    }
    public function read($name, &$value) {
        $value = intval(@$_POST[$name]);
    }
    public function write($value) {
        return strval(intval($value));
    }
}

?>
