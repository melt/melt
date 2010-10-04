<?php namespace nmvc\core;

/**
 * SelectType, the only built-in pointer type.
 */
class SelectModelType extends PointerType {
    /** @var string Where condition to filter targets. */
    public $where = "";
    /** @var string Column in target to use for labeling objects. */
    public $label_column;
    /** @var mixed FALSE = prevent dash column, TRUE = always dash column, NULL = auto, based on disconnect reaction. */
    public $dash_column = null;

    public function __construct($column_name, $target_model, $disconnect_reaction = "SET NULL", $label_column = null) {
        parent::__construct($column_name, $target_model, $disconnect_reaction);
        $this->label_column = $label_column;
    }

    /**
    * @desc The id's set here will not be selectable and treated as invalid.
    *       Useful to prevent the select from pointing to its own model.
    */
    public function denyIds(array $ids) {
        $this->denied_ids = array_merge($this->denied_ids, $ids);
    }
    private $denied_ids = array();

    protected function getWhereFilter() {
        return $this->where;
    }

    public function getInterface($name) {
        $current_id = $this->getID();
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        $results = forward_static_call(array($this->target_model, 'selectWhere'), $this->getWhereFilter());
        if (($this->dash_column !== false && ($this->dash_column === true || $this->getDisconnectReaction() != "CASCADE")) || count($results) == 0)
            $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $selected = ' selected="selected"';
        $out_list = array();
        foreach ($results as $model) {
            if (isset($model->{$this->label_column}))
                $label = escape($model->{$this->label_column});
            else
                $label = strip_tags((string) $model);
            $id = $model->getID();
            if (in_array($id, $this->denied_ids))
                continue;
            $s = ($current_id == $id)? $selected: null;
            $out_list[$label] = "<option$s value=\"$id\">$label</option>";
        }
        ksort($out_list);
        $html .= implode("", $out_list);
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
        $where = trim($this->getWhereFilter());
        if ($where != "")
            $where = "($where) AND ";
        $where .= "id = $value";
        $count = forward_static_call(array($this->target_model, 'count'), $where);
        if ($count != 1)
            $value = 0;
        $this->value = $value;
    }
}
