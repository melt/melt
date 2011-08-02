<?php namespace melt\core;

class BooleanType extends \melt\AppType {
    public $true_str = "yes";
    public $false_str = "no";

    public function get() {
        return $this->value == true;
    }

    public function getSQLType() {
        return "tinyint";
    }
    public function getSQLValue() {
        return $this->value? "1": "0";
    }
    public function getInterface($name) {
        $value = ($this->value == true)? "checked=\"checked\"": "";
        return "<input type=\"checkbox\" name=\"$name\" id=\"$name\" $value value=\"checked\" />";
    }
    public function readInterface($name) {
        $this->value = (@$_POST[$name] == "checked");
    }
    public function __toString() {
        return $this->value? $this->true_str: $this->false_str;
    }
}


