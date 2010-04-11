<?php

namespace nanomvc\userx;

class GroupSelectorType extends \nanomvc\Type {
    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name, $label) {
        $value = strval($this->value);
        $html = "$label <select name=\"$name\">";
        $selected = ' selected="selected"';
        foreach (config\getApplicationUserGroups() as $group_id => $group) {
            $s = ($value == $group_id)? $selected: null;
            $html .= "<option$s value=\"$group_id\">$group</option>";
        }
        $html .= "</select>";
        return $html;
    }

    public function readInterface($name) {
        $value = intval(@$_POST[$name]);
        $aug = config\getApplicationUserGroups();
        if (!isset($aug[$value]))
            request\show_invalid();
        $this->value = $value;
    }

    public function __toString() {
        $aug = config\getApplicationUserGroups();
        return isset($aug[$this->value])? $aug[$this->value]: "N/A";
    }
}

?>
