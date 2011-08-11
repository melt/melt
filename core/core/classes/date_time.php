<?php namespace melt\core;

class DateTime extends \DateTime {
    public function __construct($time = "now", \DateTimeZone $timezone = null) {
        if ($time instanceof \DateTime)
            $time = $time->format("r");
        if ($timezone === null)
            $timezone = new \DateTimeZone(\date_default_timezone_get());
        parent::__construct($time, $timezone);
    }
    
    public static function createFromFormat($format, $time, \DateTimeZone $timezone = null) {
        if ($timezone === null)
            $timezone = new \DateTimeZone(\date_default_timezone_get());
        return new DateTime(parent::createFromFormat($format, $time, $timezone), $timezone);
    }

    public function getDaysInMonth() {
        return \intval($this->format("t"));
    }

    public function __get($name) {
        switch ($name) {
        case "second":
             return \intval($this->format("s"));
        case "minute":
             return \intval($this->format("i"));
        case "hour":
            return \intval($this->format("G"));
        case "weekday":
            return \intval($this->format("N"));
        case "date":
            return \intval($this->format("j"));
        case "month":
            return \intval($this->format("n"));
        case "year":
            return \intval($this->format("Y"));
        }
        return $this->$name;
    }

    public function __set($name, $value) {
        switch ($name) {
        case "second":
            $this->setTime($this->hour, $this->minute, \intval($value));
            break;
        case "minute":
            $this->setTime($this->hour, \intval($value), $this->second);
            break;
        case "hour":
            $this->setTime(\intval($value), $this->minute, $this->second);
            break;
        case "weekday":
            \trigger_error("Cannot set day of week directly.", \E_USER_ERROR);
            break;
        case "date":
            $this->setDate($this->year, $this->month, \intval($value));
            break;
        case "month":
            $this->setDate($this->year, \intval($value), $this->date);
            break;
        case "year":
            $this->setDate(\intval($value), $this->month, $this->date);
            break;
        default:
            $this->$name = $value;
        }
    }
}
