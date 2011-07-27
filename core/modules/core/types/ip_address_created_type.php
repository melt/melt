<?php namespace melt\core;

/**
*@desc Stores a remote address.
*/
class IpAddressCreatedType extends IpAddressType {
    public function getInterface($name) {
        return false;
    }
    public function readInterface($name) {
        if ($this->value == null)
            $this->setToRemoteAddr();
    }
}

