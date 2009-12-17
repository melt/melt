<?php

/**
*@desc A timestamp that indicates when last changed. It does this by not providing an interface and always reading NOW.
*/
class ChangedTimestampType extends Type {
    public function getSQLType() {
        return "int";
    }
    public function getSQLValue() {
        return intval($this->value);
    }
    public function getInterface($label) {
        return false;
    }
    public function readInterface() {
        $this->value = time();
    }
    public function __toString() {
        return date('Y-m-d, H:i:s', intval($this->value));
    }

}

?>