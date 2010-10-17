<?php namespace nmvc\userx;

class PasswordType extends \nmvc\AppType {
    private $set_cleartext_password = null;

    /**
     * Returns what the password was changed to (for validation).
     * If the password wasn't changed, NULL is returned.
     * If confirmation failed, FALSE is returned.
     * @return mixed
     */
    public function getSetCleartextPassword() {
        return $this->set_cleartext_password;
    }

    public function getSQLType() {
        return "varchar(80)";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        return array(
            "<input type=\"password\" name=\"$name\" id=\"$name\" value=\"\" />",
            "<input type=\"password\" name=\"$name" . "_1\" id=\"$name" . "_1\" value=\"\" />",
        );
    }

    public function readInterface($name) {
        $new_pwd = @$_POST[$name];
        $con_pwd = @$_POST[$name . "_1"];
        if ($new_pwd == "" && $con_pwd == "") {
            $this->set_cleartext_password = null;
            return;
        } else  if ($new_pwd != $con_pwd) {
            $this->set_cleartext_password = false;
            return;
        } else {
            $this->set_cleartext_password = $new_pwd;
            $this->value = hash_password($new_pwd);
        }
    }

    public function __toString() {
        return ($this->value == "" || $this->value[0] != '$')? __("[<b>Not Set - Login Not Possible</b>]"): __('[<b>Set</b>]');
    }
}


