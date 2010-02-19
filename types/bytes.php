<?php

class BytesType extends Type {
    public function getSQLType() {
        return "int";
    }
    public function getSQLValue() {
        return intval($this->value);
    }
    public function getInterface($label) {
        return false;
    }
    public function addBytes($bytes) {
        $this->value += $bytes;
    }
    public function readInterface() {
    }
    public function __toString() {
        return api_misc::byte_unit(intval($this->value));
    }
}

?>
