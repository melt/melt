<?php

namespace nanomvc\core;

class IntegerType extends \nanomvc\Type {
    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name, $label) {
        $value = intval($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = intval(@$_POST[$name]);
    }

    public function view() {
        return strval(intval($this->value));
    }
}


