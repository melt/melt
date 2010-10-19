<?php namespace nmvc\userx;

class GroupSelectorType extends \nmvc\AppType {
    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name) {
        $value = strval($this->value);
        $html = "<select name=\"$name\" id=\"$name\">";
        $selected = ' selected="selected"';
        foreach (GroupModel::select() as $group_id => $group) {
            $s = ($value == $group_id)? $selected: null;
            $html .= "<option$s value=\"$group_id\">" . $group->name . "</option>";
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

    public function view() {
        $aug = config\getApplicationUserGroups();
        return isset($aug[$this->value])? $aug[$this->value]: "N/A";
    }
}

?>
