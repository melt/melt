<?php

namespace nanomvc\core;

define("STR_YES", __("YES"));
define("STR_NO", __("NO"));

class YesNoType extends \nanomvc\Type {
    public $true_str = null;
    public $false_str = null;

    public function getSQLType() {
        return "tinyint(1)";
    }
    public function getSQLValue() {
        return $this->value? "1": "0";
    }
    public function getInterface($name, $label) {
        $yes_check = $no_check = "selected=\"selected\"";
        if ($this->value)
            $no_check = "";
        else
            $yes_check = "";
        $yes = $this->true_str == null? STR_YES: $this->true_str;
        $no = $this->false_str == null? STR_NO: $this->false_str;
        return "$label <select name=\"$name\"><option value=\"0\" $no_check>$no</option><option value=\"1\" $yes_check>$yes</option></select>";
    }
    public function readInterface($name) {
        $this->value = (@$_POST[$name] == true);
    }
    public function view() {
        return $this->value? YES_STR: NO_STR;
    }
}


