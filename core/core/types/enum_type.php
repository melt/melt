<?php namespace melt\core;

class EnumType extends \melt\AppType {
    private $enumeration;
    private $labels = array();

    public function __construct(array $enumeration) {
        parent::__construct();
        if (\count($enumeration) == 0)
            \trigger_error("The enumeration must have at least one index.", \E_USER_ERROR);
        foreach ($enumeration as $key => &$value) {
            if (\is_integer($key))
                continue;
            $this->labels[$key] = (string) $value;
            $value = (string) $key;
        }
        $this->enumeration = \array_combine($enumeration, $enumeration);
    }

    /** Make sure GET never returns an invalid value for this type. */
    public function get() {
        if (isset($this->enumeration[$this->value])) {
            return $this->value;
        } else {
            return \reset($this->enumeration);
        }
    }

    public function set($value) {
        if (!\is_scalar($value) || !isset($this->enumeration[$value]))
            $this->value = \reset($this->enumeration);
        else
            $this->value = $value;
    }

    public function getEnum() {
        return $this->enumeration;
    }

    public function getSQLType() {
        return "ENUM(" . \implode(", ", \array_map(function($enum_token) {
            return \melt\db\strfy($enum_token);
        }, $this->enumeration)) . ")";
    }

    public function getSQLValue() {
        return \melt\db\strfy($this->get());
    }

    public function getInterface($name) {
        $html = "<select name=\"$name\" id=\"$name\">";
        $selected = ' selected="selected"';
        foreach ($this->enumeration as $key) {
            $label = isset($this->labels[$key])? escape($this->labels[$key]): escape($key);
            $s = ($this->value == $key)? $selected: null;
            $html .= "<option$s value=\"$key\">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    public function readInterface($name) {
        $this->set(@$_POST[$name]);
    }

    public function __toString() {
        $value = $this->get();
        return escape(\array_key_exists($value, $this->labels)? $this->labels[$value]: $value);
    }
}