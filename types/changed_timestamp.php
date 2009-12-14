<?php

/**
*@desc A timestamp that indicates when last changed. It does this by not providing an interface and always reading NOW.
*/
class ChangedTimestampType extends Type {
    public function getSQLType() {
        return "int";
    }
    public function SQLize($data) {
        return intval($data);
    }
    public function getInterface($label, $data, $name) {
        return false;
    }
    public function read($name, &$value) {
        $value = time();
    }
    public function write($value) {
        return date('Y-m-d, H:i:s', intval($value));
    }

}

?>