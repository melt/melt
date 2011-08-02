<?php namespace melt\mail;

/**
* @desc Wraps a single email address.
*/
class Address {
    public $email = null;
    public $name = null;

    public function set($email, $name = null) {
        $this->email = strtolower($email);
        $this->name = $name;
    }

    public function getAddress() {
        return address::getFormatedAddress($this->email, $this->name);
    }

    public static function getFormatedAddress($email, $name = null) {
        $email = trim($email);
        // Use name-addr format if name is present (name-addr = [display-name] [CFWS] "<" addr-spec ">" [CFWS]), see RFC 2822.
        if ($name != null) {
            $name = trim($name);
            $name = str_replace('"', '', $name);
            // Use MIME encoded-word syntax to transmit UTF-8 name.
            $name = "=?UTF-8?B?" . base64_encode($name) . "?=";
            $email = $name . ' <' . $email . '>';
        }
        return $email;
    }
}

