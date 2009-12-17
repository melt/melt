<?php

class FloatType extends Type {
    public function getSQLType() {
        return "float";
    }
    public function getSQLValue() {
        return floatval($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        $value = floatval($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }
    public function readInterface() {
        $this->value = floatval(@$_POST[$this->name]);
    }
    public function __toString() {
        return strval(floatval($this->value));
    }
}

?>
