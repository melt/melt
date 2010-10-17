<?php namespace nmvc\rapid;

class EnumCopyType extends \nmvc\core\TextType {
    /** @var Where condition to filter targets. */
    private $where = "";
    /** @var Targeting options. */
    private $label_column;
    private $target_model;

    public function __construct($column_name, $target_model, $label_column = null, $varchar_size = 128, $where = "") {
        parent::__construct($column_name, $varchar_size);
        $target_model = 'nmvc\\' . $target_model;
        if (!class_exists($target_model) || !is_subclass_of($target_model, 'nmvc\Model'))
            trigger_error("Attempted to declare a pointer pointing to a non existing model '$target_model'.");
        $this->target_model = $target_model;
        $this->label_column = $label_column;
        $this->where = $where;
    }

    public function getInterface($name) {
        $value = strval($this->value);
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $results = forward_static_call(array($this->target_model, 'selectWhere'), $this->where);
        $selected = ' selected="selected"';
        foreach ($results as $model) {
            if (isset($model->{$this->label_column}))
                $label = escape($model->{$this->label_column});
            else
                $label = escape((string) $model);
            $id = $model->getID();
            $s = ($value == $label)? $selected: null;
            $html .= "<option$s value=\"$id\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    public function readInterface($name) {
        $value = intval(@$_POST[$name]);
        if ($value < 1) {
            // No change.
            return;
        }
        // If this is an invalid ID, set to null.
        $where = trim($this->where);
        if ($where != "")
            $where .= " AND ";
        $selected = forward_static_call(array($this->target_model, 'selectById'), $value);
        if ($selected === null) {
            // No change
            return;
        }
        $this->value = strval($selected->{$this->label_column});
    }
}