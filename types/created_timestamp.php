<?php

/**
*@desc A timestamp that indicates when last changed. It does this by not providing an interface and always reading NOW.
*/
class CreatedTimestampType extends Type {
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
        if ($this->value <= 0)
            $this->value = time();
    }
    public function __toString() {
        return date('Y-m-d, H:i:s e', intval($this->value));
    }

}

?>