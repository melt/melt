<?php namespace nmvc\core;

/**
 * A date selector which uses jquery-ui to display a date picker.
 * It stores/gets/sets dates in ISO-8601 format (YYYY-mm-dd).
 */
class DateType extends \nmvc\AppType {
    private $varchar_size = null;

    public function __construct() {
        parent::__construct();
        $this->value = new \DateTime();
        $this->value->setTime(0, 0, 0);
    }

    public function getSQLType() {
        return "DATE";
    }

    public function get() {
        return $this->value;
    }

    public function set($value) {
        if (\is_string($value) || \is_integer($value)) {
            if (\is_string($value)) {
                $value = \preg_replace('#[^\d]#', '', $value);
                if (\strlen($value) != 8)
                    \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
                $time = @\mktime(null, null, null, \substr($value, 4, 2), \substr($value, 6, 2), \substr($value, 0, 4));
                if ($time === false)
                    \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
            } else {
                $time = $value;
            }
            $this->value = @\DateTime::createFromFormat("U", $time);
            $this->value->setTime(0, 0, 0);
            if ($this->value === false)
                \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
        } else if ($value instanceof \DateTime) {
            $this->value = $value;
            $this->value->setTime(0, 0, 0);
        } else {
            \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
        }
    }

    public function setSQLValue($value) {
        $this->value = @\DateTime::createFromFormat('Y-m-d', $value);
        $this->value->setTime(0, 0, 0);
        if ($this->value === false)
            $this->value = new \DateTime();
    }

    public function getSQLValue() {
        return \nmvc\db\strfy($this->value->format("Y-m-d"));
    }

    public function getInterface($name) {
        $value = escape($this->value->format("Y-m-d"));
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
        return escape($this->value->format("Y-m-d"));
    }
}
