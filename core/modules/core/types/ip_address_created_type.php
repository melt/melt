<?php

namespace nmvc\core;

/**
*@desc Stores a remote address.
*/
class IpAddressCreatedType extends RemoteAddressType {
    public function getInterface($name) {
        return false;
    }
    public function readInterface($name) {
        if ($this->value == null)
            $this->setToRemoteAddr();
    }
}

