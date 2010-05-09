<?php

namespace nmvc\core;

/**
*@desc Stores a remote address.
*/
class IpAddressType extends \nmvc\Type {
    public function getSQLType() {
        // Enough to store IPv6.
        return "varbinary(16)";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        $value = inet_ntop($this->value);
        return "<input type=\"text\" name=\"$name\" value=\"$value\" />";
    }

    /**
     * Sets this ip address to the current remote IP address.
     */
    public function setToRemoteAddr() {
        $this->value = inet_pton($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Sets this up address to the specified human-readable IP address.
     * @param string $ip_addr IPv4 or IPv6 (human-readable) IP address
     */
    public function setToAddr($ip_addr) {
        $this->value = inet_pton($ip_addr);
    }

    public function readInterface($name) {
        $this->value = inet_pton(@$_POST[$name]);
    }

    public function view() {
        return strval(inet_ntop($this->value));
    }
}

