<?php namespace melt\cache;

/**
 * Tag module - only designed to be used inside cache module.
 */
class Str8Type extends \melt\AppType {    
    public function getSQLType() {
        return "varchar(8)";
    }
    
    public function getSQLValue() {
        return \melt\db\strfy($this->value);
    }

    public function  __toString() {
        return escape($this->value);
    }

    public function getInterface($name) { }

    public function readInterface($name) { }
}
