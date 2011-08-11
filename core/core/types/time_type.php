<?php namespace melt\core;

/**
 * A date selector which uses jquery-ui to display a date picker.
 * It stores/gets/sets dates in ISO-8601 format (YYYY-mm-dd).
 */
class TimeType extends DateType {
    public function __construct() {
        parent::__construct();
        $this->resetDate();
    }
    
    private function resetDate() {
        $this->value->setDate(1970, 1, 1);
    }
    
    public function getSQLType() {
        return "TIME";
    }
    
    public function set($value) {
        if (\is_string($value) || \is_integer($value)) {
            if (\is_string($value)) {
                $value = \preg_replace('#[^\d]#', '', $value);
                if (\strlen($value) === 6) {
                    $this->value->setTime(substr($value, 0, 2), substr($value, 2, 2), substr($value, 4, 2));
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
            $this->resetDate();
        } else {
            \trigger_error(__CLASS__ . " did not understand the given Date!", \E_USER_ERROR);
        }
    }

    public function getSQLValue() {
        return \melt\db\strfy($this->value->format("H:i:s"));
    }

    public function getInterface($name) {
        $value = escape($this->value->format("H:i:s"));
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function __toString() {
        return escape($this->value->format("H:i:s"));
    }
}
