<?php namespace nmvc\mail;

/**
* @desc Wraps a list of email addressess.
*/
class AddressList {
    private $list = array();
    private $plainlist = array();

    public function count() {
        return count($this->list);
    }

    /**
    * @desc Adds an email address to the list of repicents.
    * @param String $email RFC compatible e-mail address.
    * @param String $name Literal name of repicent, expects this to be UTF-8 encoded.
    */
    public function add($email, $name = null) {
        $email = trim($email);
        $this->plainlist[] = $email;
        $this->list[] = address::getFormatedAddress($email, $name);
    }

    public function getAsHeader($header, $plain = false) {
        if (count($this->list) == 0)
            return null;
        else
            return "$header: " . ($plain? $this->getPlainList(): $this->getList()) . Smtp::CRLF;
    }

    public function getPlainArray() {
        return $this->plainlist;
    }

    public function getList() {
        return implode(', ', $this->list);
    }

    public function getPlainList() {
        return implode(', ', $this->plainlist);
    }
}

