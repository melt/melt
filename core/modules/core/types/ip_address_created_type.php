<?php

namespace nanomvc\core;

/**
*@desc Stores a remote address.
*/
class IpAddressCreatedType extends RemoteAddressType {
    public function getInterface($name, $label) {
        return false;
    }
    public function readInterface($name) {
        if ($this->value == null)
            $this->setToRemoteAddr();
    }
}

