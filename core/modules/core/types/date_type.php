<?php namespace nmvc\core;

/**
 * A date selector which uses jquery-ui to display a date picker.
 * It stores/gets/sets dates in ISO-8601 format (YYYY-mm-dd).
 */
class DateType extends \nmvc\AppType {
    private $varchar_size = null;

    public function __construct($column_name) {
        parent::__construct($column_name);
        $this->value = null;
    }

    public function getSQLType() {
        return "DATE";
    }

    public function get() {
        if (is_string($this->value))
            return $this->value;
        else
            return date("Y-m-d");
    }

    public function set($value) {
        $value = \preg_replace('#[^\d]#', '', $value);
        if (\strlen($value) == 8)
            $this->value = date('Y-m-d', mktime(null, null, null, substr($value, 4, 2), substr($value, 6, 2), substr($value, 0, 4)));
        else
            $this->value = null;
    }

    public function getSQLValue() {
        return \nmvc\db\strfy($this->get());
    }

    public function getInterface($name) {
        $value = escape($this->get());
        $maxlength = null;
        if ($this->varchar_size !== null)
            $maxlength = "maxlength=\"" . $this->varchar_size . "\"";
        return "<input type=\"text\" $maxlength name=\"$name\" id=\"$name\" value=\"$value\" />"
        . "<script type=\"text/javascript\">$(function() { $('#$name').datepicker({ dateFormat: 'yy-mm-dd' }); });</script>";
    }

    public function readInterface($name) {
        $this->set(@$_POST[$name]);
    }

    public function __toString() {
        return escape(strval($this->get()));
    }
}
