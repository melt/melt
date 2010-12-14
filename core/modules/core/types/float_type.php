<?php namespace nmvc\core;

class FloatType extends \nmvc\AppType {
    public function getSQLType() {
        return "double";
    }

    public function get() {
        return floatval($this->value);
    }

    public function getSQLValue() {
        return floatval($this->value);
    }

    public function getInterface($name) {
        $value = floatval($this->value);
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function readInterface($name) {
        $this->value = floatval(@$_POST[$name]);
    }

    public function __toString() {
        return strval(floatval($this->value));
    }
}


