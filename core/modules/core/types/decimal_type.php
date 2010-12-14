<?php namespace nmvc\core;

/**
 * For fixed point, decimal storage.
 * This type is recommended to be used with BC Math Functions.
 * @see http://www.php.net/manual/en/ref.bc.php
 */
class DecimalType extends \nmvc\AppType {
    public $precision = 20;
    public $scale = 10;

    public function __construct($column_name, $precision = 20, $scale = 10) {
        parent::__construct($column_name);
        $this->precision = \intval($precision);
        $this->scale = \intval($precision);
        if ($this->precision < 0)
            \trigger_error("The precision must be a positive integer.", \E_USER_ERROR);
        if ($this->scale < 0 || $this->scale > $this->precision)
            \trigger_error("The scale must be a positive integer that is smaller or equal to the precision.", \E_USER_ERROR);
    }

    public function getSQLType() {
        return "DECIMAL($this->precision,$this->scale)";
    }

    public function set($value) {
        $this->value = \bcadd($value, "0", $this->scale);
    }

    public function getSQLValue() {
        return \nmvc\db\strfy($this->value);
    }

    public function getInterface($name) {
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$this\" />";
    }

    public function readInterface($name) {
        $this->set(@$_POST[$name]);
    }

    public function __toString() {
        // Remove unnecessary begin or ending zeros.
        $strvalue = (string) $this->value;
        $strvalue = \ltrim($strvalue, "0");
        if (\strpos($strvalue, ".") !== false)
            $strvalue = \rtrim($strvalue, "0.");
        return $strvalue !== ""? $strvalue: "0";
    }
}


