<?php

namespace nanomvc\core;

class FloatType extends \nanomvc\Type {
    public function getSQLType() {
        return "float";
    }

    public function getSQLValue() {
        return floatval($this->value);
    }

    public function getInterface($name, $label) {
        $value = floatval($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = floatval(@$_POST[$name]);
    }

    public function view() {
        return strval(floatval($this->value));
    }
}


