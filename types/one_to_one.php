<?php

class OneToOneType extends Type {
    public $model = "";
    public $where = "";
    public $label_column = "";

    public function getSQLType() {
        return "int";
    }
    public function SQLize($data) {
        return intval($data);
    }
    public function getInterface($label, $data, $name) {
        $html = "$label <select name=\"$name\">";
        $nothing = __("Nothing Selected");
        $html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $results = forward_static_call(array($this->model, 'selectWhere'), $this->where);
        $selected = ' selected="selected"';
        foreach ($results as $model) {
            $label = api_html::escape($model->{$this->label_column});
            $id = $model->getID();
            $s = ($data == $id)? $selected: null;
            $html .= "<option$s value=\"$id\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }
    public function read($name, &$value) {
        $value = intval(@$_POST[$name]);
        if ($value < 0)
            $value = 0;
        if ($value == 0)
            return;
        // If this is an invalid ID, set to null.
        $where = trim($this->where);
        if ($where != "")
            $where .= " AND ";
        $where .= "id = $value";
        $count = forward_static_call(array($this->model, 'count'), $where);
        if ($count != 1)
            $value = 0;
    }
    public function write($value) {
        return ($value > 0)? $this->model . " #" . intval($value): __("Not Set");
    }
}

?>
