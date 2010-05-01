<?php

namespace nanomvc\core;

/** SelectType, for selecting multiple options. */
class SelectType extends \nanomvc\Type {
    /** @var Where condition to filter targets. */
    public $options = array();

    public function getInterface($name, $label) {
        $html = "$label <select name=\"$name\">";
        $nothing = __("Nothing Selected");
        //$html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $selected = ' selected="selected"';
        foreach ($this->options as $option_key => $option_val) {
            $label = escape($option_val);
            $id = escape($option_key);
            $s = ($this->value == $id)? $selected: null;
            $html .= "<option$s value=\"$id\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    /** Make sure GET never returns an invalid value for this type. */
    public function get() {
        if (isset($this->options[$this->value])) {
            return $this->value;
        } else {
            reset($this->options);
            key($this->options);
        }
    }

    public function readInterface($name) {
        $this->value = @$_POST[$name];
        if (!isset($this->options[$this->value]))
            $this->value = null;
    }

    public function view() {
        $set = isset($this->options[$this->value]);
        return $set? "'" . escape($this->options[$this->value]) . "'": __("Not Set");
    }

    public function getSQLType() {
        return "varchar(64)";
    }

    public function getSQLValue() {
        return strfy(serialize($this->value));
    }

    public function setSQLValue($value) {
        $this->value = unserialize($value);
    }
}
