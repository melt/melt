<?php

namespace nanomvc\core;

/**
*@desc Stores a remote address.
*/
class IpAddressChangedType extends RemoteAddressType {
    public function getInterface($name, $label) {
        return false;
    }
    public function readInterface($name) {
        $this->setToRemoteAddr();
    }
}

