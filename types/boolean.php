<?php

class BooleanType extends Type {
    public $true_str = "yes";
    public $false_str = "no";

    public function getSQLType() {
        return "boolean";
    }
    public function getSQLValue() {
        return $this->value? "TRUE": "FALSE";
    }
    public function getInterface($label) {
        $name = $this->name;
        $value = ($this->value == true)? "checked=\"checked\"": "";
        return "<input type=\"checkbox\" name=\"$name\" $value value=\"checked\" /> $label";
    }
    public function readInterface() {
        $this->value = (@$_POST[$this->name] == "checked");
    }
    public function __toString() {
        return $this->value? $this->true_str: $this->false_str;
    }
}

?>
