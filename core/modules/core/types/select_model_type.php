<?php namespace melt\core;

class SelectModelType extends PointerType {
    /** @var string Column in target to use for labeling objects. */
    public $label_column;
    /** @var mixed FALSE = prevent dash column, TRUE = always dash column, NULL = auto, based on disconnect reaction. */
    public $dash_column = null;
    /** @var string Null options will use all possible instances as options,
     * otherwise a function in parent instance that returns a where condition
     * which filters possible options. */
    public $options = null;

    public function __construct($target_model, $disconnect_reaction = "SET NULL", $label_column = null) {
        parent::__construct($target_model, $disconnect_reaction);
    }

    protected function getOptions() {
        $target_model = $this->target_model;
        if ($this->options === null)
            return $target_model::select();
        if (!\is_callable(array($this->parent, $this->options)))
            \trigger_error(__CLASS__ . " configured incorrectly! Parent " . \get_class($this->parent) . " has no function " . $this->options, \E_USER_ERROR);
        $options = \call_user_func(array($this->parent, $this->options), $target_model);
        if (!($options instanceof \melt\db\WhereCondition))
            \trigger_error(__CLASS__ . " configured incorrectly! Parent " . \get_class($this->parent) . " did not return melt\db\WhereCondition as expected.", \E_USER_ERROR);
        return $target_model::select()->where($options);
    }

    public function getInterface($name) {
        $current_id = $this->getID();
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        $results = $this->getOptions()->all();
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
        if ($this->getOptions()->and("id")->is($value)->count() == 0)
            $value = 0;
        $this->value = $value;
    }
}
