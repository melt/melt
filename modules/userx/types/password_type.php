<?php namespace nmvc\userx;

class PasswordType extends \nmvc\Type {
    private $password_change_status = null;

    /**
     * Returns the password change status.
     * null = No change has taken place.
     * true = Password successfully changed.
     * false = Pasword incorrect. Change failed.
     * @return mixed
     */
    public function getPasswordChangeStatus() {
        return $this->password_change_status;
    }

    public function getSQLType() {
        return "varchar(128)";
    }

    public function getSQLValue() {
        return strfy($this->value);
    }

    public function getInterface($name) {
        $cur_pwd = __("Current Password");
        $new_pwd = __("New Password");
        $con_pwd = __("Confirm Password");
        return "<span>$cur_pwd</span><input type=\"password\" name=\"cur_$name\" value=\"\" />"
        . "<span>$new_pwd</span><input type=\"password\" name=\"$name\" value=\"\" />"
        . "<span>$con_pwd</span><input type=\"password\" name=\"con_$name\" value=\"\" />";
    }

    public function readInterface($name) {
        $cur_pwd = $_POST["cur_" . $name];
        $new_pwd = $_POST[$name];
        $con_pwd = $_POST["con_" . $name];
        // Validate change.
        if (!validate_password($this->value, $cur_pwd) || $new_pwd != $con_pwd) {
             $this->password_change_status = false;
             return;
        }
        $this->value = hash_password($cur_pwd);
        $this->password_change_status = true;
    }
    
    public function view() {
        return "<i>Hidden</i>";
    }
}


