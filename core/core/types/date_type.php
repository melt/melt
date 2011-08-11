<?php namespace melt\core;

/**
 * A date selector which uses jquery-ui to display a date picker.
 * It stores/gets/sets dates in ISO-8601 format (YYYY-mm-dd).
 */
class DateType extends \melt\AppType {
    public $parsing_failed = false;
    
    public function __construct() {
        parent::__construct();
        $this->resetDateTime();
    }
    
    protected function resetDateTime() {
        $this->value = DateTime::createFromFormat("U", 0);
    }
    
    protected function resetTime() {
        $this->value->setTime(0, 0, 0);
    }

    public function getSQLType() {
        return "DATE";
    }

    public function get() {
        return new DateTime($this->value);
    }

    public function set($value) {
        if (\is_string($value) || \is_integer($value)) {
            if (\is_string($value)) {
                $value = \preg_replace('#[^\d]#', '', $value);
                if (\strlen($value) === 8) {
                    $this->value->setDate(\substr($value, 0, 4), \substr($value, 4, 2), \substr($value, 6, 2));
                } else {
                    $this->parsing_failed = true;
                }
            } else {
                $this->value = @DateTime::createFromFormat("U", $value);
                if ($this->value === false) {
                    $this->resetDateTime();
                    $this->parsing_failed = true;
                } else {
                    $this->resetTime();
                }
            }
        } else if ($value instanceof \DateTime) {
            $this->value = new DateTime($value);
            $this->resetTime();
        } else {
            \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
        }
    }
    
    public function setSQLValue($value) {
        $this->resetTime();
        $this->set($value);
    }

    public function getSQLValue() {
        return \melt\db\strfy($this->value->format("Y-m-d"));
    }

    public function getInterface($name) {
        $value = escape($this->value->format("Y-m-d"));
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />"
        . "<script type=\"text/javascript\">$(function() { $('#$name').datepicker({ dateFormat: 'yy-mm-dd' }); });</script>";
    }

    public function readInterface($name) {
        $this->set(@$_POST[$name]);
    }

    public function __toString() {
        return escape($this->value->format("Y-m-d"));
    }
}
