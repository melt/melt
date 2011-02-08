<?php namespace nmvc\core;

class SerializedType extends \nmvc\AppType {
    public function __construct() {
        parent::__construct();
        $this->value = null;
    }

    public function getSQLType() {
        return "text";
    }
    
    public function getSQLValue() {
        return $this->value === null? "''": \nmvc\db\strfy(\serialize($this->value));
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
