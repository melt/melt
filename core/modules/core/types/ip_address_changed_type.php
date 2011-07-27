<?php namespace melt\core;

/**
*@desc Stores a remote address.
*/
class IpAddressChangedType extends IpAddressType {
    public function getInterface($name) {
        return false;
    }
    public function readInterface($name) {
        $this->setToRemoteAddr();
    }
}

