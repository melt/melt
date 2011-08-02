<?php namespace melt\userx;

class PasswordType extends \melt\AppType {
    public function getSQLType() {
        return "varchar(80)";
    }

    private $stored_hashed_value = null;

    public function setSQLValue($value) {
        $this->stored_hashed_value = $value;
        $this->value = null;
    }

    public function getStoredHashedValue() {
        return $this->stored_hashed_value;
    }

    public function getSQLValue() {
        if (\is_string($this->value))
            return \melt\db\strfy(hash_password($this->value));
        else
            return \melt\db\strfy($this->stored_hashed_value);
    }

    public function getInterface($name) {
        $value = \is_string($this->value)? escape($this->value): "";
        $ret = array("<input type=\"password\" name=\"$name\" id=\"$name\" value=\"$value\" />");
        if (!$this->parent->isVolatile())
            $ret[] = "<input type=\"password\" name=\"$name" . "_1\" id=\"$name" . "_1\" value=\"$value\" />";
        return $ret;
    }

    public function readInterface($name) {
        $new_pwd = @$_POST[$name];
        if ($new_pwd == "") {
            $this->value = null;
            return;
        } else if (isset($_POST[$name . "_1"]) && $new_pwd != $_POST[$name . "_1"]) {
            $this->value = false;
            return;
        }
        $this->value = $new_pwd;
    }

    public function __toString() {
        return ($this->value == "" || $this->value[0] != '$')? __("[<b>Not Set - Login Not Possible</b>]"): __('[<b>Set</b>]');
    }
}


