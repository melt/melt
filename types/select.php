<?php

class SelectType extends Reference {
    /**
    * @desc Where condition to filter targets.
    */
    public $where = "";
    /**
    * @desc Column in target to use for labeling objects.
    */
    public $label_column = "";

    private $denied_ids = array();

    /**
    * @desc The id's set here will not be selectable and treated as invalid.
    *       Useful to prevent the select from pointing to its own model.
    */
    public function denyIds(array $ids) {
        $this->denied_ids += $ids;
    }

    public function getInterface($label) {
        $name = $this->name;
        $value = intval($this->value);
        $html = "$label <select name=\"$name\">";
        $nothing = __("Nothing Selected");
        $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $results = forward_static_call(array($this->to_model, 'selectWhere'), $this->where);
        $selected = ' selected="selected"';
        foreach ($results as $model) {
            $label = api_html::escape($model->{$this->label_column});
            $id = $model->getID();
            if (in_array($id, $this->denied_ids))
                continue;
            $s = ($value == $id)? $selected: null;
            $html .= "<option$s value=\"$id\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }
    public function readInterface() {
        $value = intval(@$_POST[$this->name]);
        if ($value < 1) {
            $this->value = 0;
            return;
        }
        // If this is an invalid ID, set to null.
        $where = trim($this->where);
        if ($where != "")
            $where .= " AND ";
        $where .= "id = $value";
        $count = forward_static_call(array($this->to_model, 'count'), $where);
        if ($count != 1 || ($this->may_self_target != "true" && $value == $this->value))
            $value = 0;
        $this->value = $value;
    }
    public function __toString() {
        $target = $this->ref();
        if (is_object($target))
            if (in_array($target->getID(), $this->denied_ids))
                $this->value = 0;
            else
                $label = empty($this->label_column)? $this->to_model: $target->{$this->label_column}->get();
        else
            $this->value = 0;
        return ($this->value > 0)? $label . " (#" . intval($this->value) . ")": __("Not Set");
    }
}

?>
