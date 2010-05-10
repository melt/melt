<?php namespace nmvc\cache;

/**
 * Tag module - only designed to be used inside cache module.
 */
class Str8Type extends \nmvc\AppType {    
    public function getSQLType() {
        return "varchar(8)";
    }
    
    public function getSQLValue() {
        return strfy($this->value);
    }

    public function view() {
        return escape($this->value);
    }

    public function getInterface($name) { }

    public function readInterface($name) { }
}
