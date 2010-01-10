<?php
/**
*@desc Records the remote endpoint that modifies a model.
*/
class RemoteAddressType extends Type {
    public function getSQLType() {
        return "varchar(64)";
    }
    public function getSQLValue() {
        return api_database::strfy($this->value);
    }
    public function getInterface($label) {
        return false;
    }
    public function readInterface() {
        $this->value = $_SERVER['REMOTE_ADDR'];
    }
    public function __toString() {
        return strval($this->value);
    }

}
?>
