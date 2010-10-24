<?php namespace nmvc\userx;

/**
 * Interface that automatically
 */
class PasswordType extends \nmvc\AppType {
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
            return \nmvc\db\strfy(hash_password($this->value));
        else
            return \nmvc\db\strfy($this->stored_hashed_value);
    }

    public function getInterface($name) {
        $ret = array("<input type=\"password\" name=\"$name\" id=\"$name\" value=\"\" />");
        if (!$this->parent->isVolatile())
            $ret[] = "<input type=\"password\" name=\"$name" . "_1\" id=\"$name" . "_1\" value=\"\" />";
        return $ret;
    }

    public function readInterface($name) {
        $new_pwd = @$_POST[$name];
        $con_pwd = @$_POST[$name . "_1"];
        if ($new_pwd == "" && $con_pwd == "") {
            $this->value = null;
            return;
        } else if ($new_pwd != $con_pwd) {

        }
        $this->value = $new_pwd;
    }

    public function __toString() {
        return ($this->value == "" || $this->value[0] != '$')? __("[<b>Not Set - Login Not Possible</b>]"): __('[<b>Set</b>]');
    }
}

