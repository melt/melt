<?php namespace nmvc\core;

class EnumCopyType extends \nmvc\core\TextType {
    /** @var \nmvc\db\SelectQuery Target model selection. */
    private $selection;
    /* Targeting options. */
    private $label_column;
    private $target_model;

    public function __construct($target_model, $label_column = null, $varchar_size = 128) {
        parent::__construct($varchar_size);
        $target_model = 'nmvc\\' . $target_model;
        if (!class_exists($target_model) || !is_subclass_of($target_model, 'nmvc\Model'))
            trigger_error("Attempted to declare a pointer pointing to a non existing model '$target_model'.");
        $this->target_model = $target_model;
        $this->label_column = $label_column;
        $this->selection = $target_model::select();
    }

    public function getInterface($name) {
        $value = strval($this->value);
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $results = $this->selection->all();
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
        // If this is an invalid ID, set to nothing.
        $selection = clone $this->selection;
        $selected = $selection->and("id")->is($value)->first();
        if ($selected === null)
            $this->value = "";
        else if ($this->label_column == "")
            $this->value = (string) $selected;
        else
            $this->value = \strval($selected->{$this->label_column});
    }
}