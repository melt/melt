<?php namespace nmvc\core;

class SerializedType extends \nmvc\AppType {
    public function __construct($column_name) {
        parent::__construct($column_name);
        $this->value = null;
    }

    public function getSQLType() {
        return "text";
    }
    
    public function getSQLValue() {
        return $this->value === null? "": strfy(\serialize($this->value));
    }


    public function setSQLValue($value) {
        if ($value == "")
            $this->value = null;
        else
            $this->value = \unserialize($value);
    }
    
    public function getInterface($name) {
        return null;
    }

    public function readInterface($name) {
        return;
    }
}
