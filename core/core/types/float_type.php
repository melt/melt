<?php namespace melt\core;

class FloatType extends \melt\AppType {
    protected $value = 0;

    public function getSQLType() {
        return "double";
    }

    public function set($value) {
        $this->value = \floatval($value);
    }

    public function getSQLValue() {
        return \strval(\floatval($this->value));
    }

    public function getInterface($name) {
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$this->value\" />";
    }

    public function readInterface($name) {
        $this->value = \floatval(@$_POST[$name]);
    }

    public function __toString() {
        return \strval($this->value);
    }
}


