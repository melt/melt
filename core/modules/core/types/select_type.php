<?php namespace nmvc\core;

/** SelectType, for selecting multiple options. */
class SelectType extends \nmvc\AppType {
    private $options;
    private $hash_to_key_map = array();
    private $key_to_hash_map = array();

    public function __construct($options = null) {
        parent::__construct();
        $this->options = $options;
        if (!\is_string($options))
            $this->finalizeOptions();
    }

    private function keyToHash($key) {
        if (!\is_scalar($key))
            return 0;
        if (\is_bool($key)) {
            $khash = $key? 1: 0;
        } else if (\is_integer($key)) {
            $khash = $key;
        } else {
            $khash = \unpack("l", \sha1($key, true));
            $khash = \reset($khash);
        }
        return $khash;
    }

    private function finalizeOptions() {
        if (!is_array($this->options) || count($this->options) == 0)
            \trigger_error(__CLASS__ . " expects an array array of at least one option. Got: " . gettype($this->options), \E_USER_ERROR);
        foreach ($this->options as $key => $value) {
            if (!\is_scalar($key))
                \trigger_error("All keys in the " . __CLASS__ . " options array has to be scalar. Found: " . gettype($key));
            $khash = $this->keyToHash($key);
            if (\array_key_exists($khash, $this->hash_to_key_map))
                \trigger_error("Hash collision for two keys! You should avoid using strings as keys as their corresponding stored value is undefined. Use integers instead.", \E_USER_ERROR);
            $this->hash_to_key_map[$khash] = $key;
            $this->key_to_hash_map[$key] = $khash;
        }
    }

    private function prepareOptions() {
        if (\is_array($this->options))
            return;
        if (!\is_callable(array($this->parent, $this->options)))
            \trigger_error(__CLASS__ . " configured incorrectly! Parent " . \get_class($this->parent) . " has no function " . $this->options, \E_USER_ERROR);
        $this->options = \call_user_func(array($this->parent, $this->options));
        $this->finalizeOptions();
    }

    public function getInterface($name) {
        $this->prepareOptions();
        $html = "<select name=\"$name\" id=\"$name\">";
        $nothing = __("—");
        //$html .= "<option style=\"font-style: italic;\" value=\"0\">$nothing</option>";
        $selected = ' selected="selected"';
        foreach ($this->options as $option_key => $option_val) {
            $label = escape($option_val);
            $khash = escape($this->key_to_hash_map[$option_key]);
            $s = ($this->value == $option_key)? $selected: null;
            $html .= "<option$s value=\"$khash\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    /** Make sure GET never returns an invalid value for this type. */
    public function get() {
        $this->prepareOptions();
        if (isset($this->key_to_hash_map[$this->value])) {
            return $this->value;
        } else {
            return \reset($this->hash_to_key_map);
        }
    }

    public function set($value) {
        $this->prepareOptions();
        if (!\is_scalar($value) || !isset($this->key_to_hash_map[$value]))
            $this->value = \reset($this->hash_to_key_map);
        else
            $this->value = $value;
    }

    public function readInterface($name) {
        $this->prepareOptions();
        $khash = @$_POST[$name];
        if (isset($this->hash_to_key_map[$khash]))
            $this->value = $this->hash_to_key_map[$khash];
    }

    public function getLabel() {
        $this->prepareOptions();
        return  isset($this->options[$this->value])? $this->options[$this->value]: null;
    }

    public function __toString() {
        $this->prepareOptions();
        return escape($this->getLabel());
    }

    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        $this->prepareOptions();
        $value = $this->get();
        return $this->key_to_hash_map[$value];
    }

    public function setSQLValue($value) {
        $this->prepareOptions();
        if (isset($this->hash_to_key_map[$value]))
            $this->value = $this->hash_to_key_map[$value];
        else
            $this->value = \reset($this->hash_to_key_map);
    }
}
