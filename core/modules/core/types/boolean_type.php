<?php

namespace nanomvc\core;

class BooleanType extends \nanomvc\Type {
    public $true_str = "yes";
    public $false_str = "no";

    public function getSQLType() {
        return "boolean";
    }
    public function getSQLValue() {
        return $this->value? "TRUE": "FALSE";
    }
    public function getInterface($name, $label) {
        $value = ($this->value == true)? "checked=\"checked\"": "";
        return "<input type=\"checkbox\" name=\"$name\" $value value=\"checked\" /> $label";
    }
    public function readInterface($name) {
        $this->value = (@$_POST[$name] == "checked");
    }
    public function view() {
        return $this->value? $this->true_str: $this->false_str;
    }
}


