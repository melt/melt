<?php

namespace nmvc\core;

/**
 * SelectType, the only built-in reference type.
 */
class SelectModelType extends \nmvc\Reference {
    /** @var Where condition to filter targets. */
    public $where = "";
    /** @var Column in target to use for labeling objects. */
    public $label_column = "";

    /**
    * @desc The id's set here will not be selectable and treated as invalid.
    *       Useful to prevent the select from pointing to its own model.
    */
    public function denyIds(array $ids) {
        $this->denied_ids = array_merge($this->denied_ids, $ids);
    }
    private $denied_ids = array();

    public function getInterface($name) {
        $value = intval($this->value);
        $html = "<select name=\"$name\">";
        $nothing = __("Nothing Selected");
        $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $results = forward_static_call(array($this->target_model, 'selectWhere'), $this->where);
        $selected = ' selected="selected"';
        foreach ($results as $model) {
            if (isset($model->{$this->label_column}))
                $label = escape($model->{$this->label_column});
            else
                $label = escape((string) $model);
            $id = $model->getID();
            if (in_array($id, $this->denied_ids))
                continue;
            $s = ($value == $id)? $selected: null;
            $html .= "<option$s value=\"$id\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    public function readInterface($name) {
        $value = intval(@$_POST[$name]);
        if ($value < 1) {
            $this->value = 0;
            return;
        }
        // If this is an invalid ID, set to null.
        $where = trim($this->where);
        if ($where != "")
            $where .= " AND ";
        $where .= "id = $value";
        $count = forward_static_call(array($this->target_model, 'count'), $where);
        if ($count != 1)
            $value = 0;
        $this->value = $value;
    }

    public function view() {
        $target = $this->ref();
        if (is_object($target))
            if (in_array($target->getID(), $this->denied_ids))
                $this->value = 0;
            else
                $label = empty($this->label_column)? $this->target_model: $target->{$this->label_column}->get();
        else
            $this->value = 0;
        return ($this->value > 0)? $label . " (#" . intval($this->value) . ")": __("Not Set");
    }
}
