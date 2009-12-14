<?php

class BooleanType extends Type {
    public $true_str = "yes";
    public $false_str = "no";

    public function getSQLType() {
        return "boolean";
    }
    public function SQLize($data) {
        return $data? "TRUE": "FALSE";
    }
    public function getInterface($label, $data, $name) {
        $data = ($data == true)? "checked=\"checked\"": "";
        return "<input type=\"checkbox\" name=\"$name\" $data value=\"checked\" /> $label";
    }
    public function read($name, &$value) {
        $value = (@$_POST[$name] == "checked");
    }
    public function write($value) {
        return $value? $this->true_str: $this->false_str;
    }
}

?>
