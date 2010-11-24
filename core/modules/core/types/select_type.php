<?php namespace nmvc\core;

/** SelectType, for selecting multiple options. */
class SelectType extends \nmvc\AppType {
    /** @var Where condition to filter targets. */
    private $options;

    public function __construct($column_name, $options = null) {
        parent::__construct($column_name);
        $this->options = $options;
        if (!\is_string($options))
            $this->checkOptions();
    }

    private function checkOptions() {
        if (!is_array($this->options) || count($this->options) == 0)
            trigger_error(__CLASS_ . " expects an array array of at least one option. Got: " . gettype($this->options), \E_USER_ERROR);
        foreach ($this->options as $k => $v) {
            if (!is_integer($k))
                trigger_error("All keys in the " . __CLASS__ . " options array has to be integers. Found: " . gettype($k));
        }
    }

    private function prepareOptions() {
        if (\is_array($this->options))
            return;
        if (!\is_callable(array($this->parent, $this->options)))
            \trigger_error(__CLASS__ . " configured incorrectly! Parent " . \get_class($this->parent) . " has no function " . $this->options, \E_USER_ERROR);
        $this->options = \call_user_func(array($this->parent, $this->options));
        $this->checkOptions();
    }

    public function getInterface($name) {
        $this->prepareOptions();
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        //$html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $selected = ' selected="selected"';
        foreach ($this->options as $option_key => $option_key) {
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
        $this->prepareOptions();
        if (isset($this->options[$this->value])) {
            return intval($this->value);
        } else {
            reset($this->options);
            return key($this->options);
        }
    }

    public function set($value) {
        $this->prepareOptions();
        $value = intval($value);
        if (!isset($this->options[$value]))
            trigger_error("Value '$value' is not a valid integer key for " . __CLASS__ . ".", \E_USER_ERROR);
        $this->value = $value;
    }

    public function readInterface($name) {
        $this->prepareOptions();
        $this->value = @$_POST[$name];
        if (!isset($this->options[$this->value]))
            $this->value = null;
    }

    public function __toString() {
        $this->prepareOptions();
        $set = isset($this->options[$this->value]);
        return $set? "'" . escape($this->getLabel($this->value)) . "'": __("Not Set");
    }

    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function setSQLValue($value) {
        $this->value = intval($value);
    }
}
