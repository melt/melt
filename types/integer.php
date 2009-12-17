<?php

class IntegerType extends Type {
    public function getSQLType() {
        return "int";
    }
    public function getSQLValue() {
        return intval($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        $value = intval($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }
    public function readInterface() {
        $this->value = intval(@$_POST[$this->name]);
    }
    public function __toString() {
        return strval(intval($this->value));
    }
}

?>
