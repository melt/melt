<?php namespace nmvc\userx;

abstract class UserModel_app_overrideable extends \nmvc\AppModel {
    /* This field is only used when config\MULTIPLE_GROUPS == false */
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');
    /* This field is only used when config\MULTIPLE_IDENTITIES == false */
    public $username = array('core\TextType', 128);

    public $password = 'userx\PasswordType';
    public $last_login_time = 'core\TimestampType';
    public $last_login_ip = 'core\IpAddressType';
    public $user_remember_key = 'core\PasswordType';
    public $user_remember_key_expires = 'core\TimestampType';
    public $disabled = 'core\YesNoType';

    /**
     * Overridable callback event method.
     * Called for every request the user has authorized
     * and logged in. If this function returns a string instead of NULL,
     * the string will be flashed as an error message
     * and the login will fail.
     * @return string NULL if session should not be touched or string to
     * tear down the session and display error message.
     */
    public function sessionValidate() {
        if ($this->disabled)
            return "This account has been disabled.";
    }

    /**
     * Sets a password for this user.
     * @param string $new_password Cleartext password.
     * @return void
     */
    public function setPassword($new_password) {
        $this->password = hash_password($new_password);
    }

    public function validate() {
        $err = array();
        $set_cleartext_password = $this->type("password")->getSetCleartextPassword();
        if ($set_cleartext_password === null && $this->password == "")
            $err["password"] = __("You must enter a password.");
        else if ($set_cleartext_password === false)
            $err["password"] = __("The password confirmation did not match.");
        if (!isset($this->group))
            $err["group"] = __("You must set a group for the user.");
        return $err;
    }
}
