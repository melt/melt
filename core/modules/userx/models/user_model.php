<?php namespace nmvc\userx;

abstract class UserModel_app_overrideable extends \nmvc\AppModel implements \nmvc\qmi\UserInterfaceProvider {
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

    public $remember_login = array(VOLATILE_FIELD, 'core\BooleanType');

    protected function initialize() {
        parent::initialize();
        if ($this->isVolatile()) {
            $this->username = @$_COOKIE["LAST_USER"];
            $this->remember_login = isset($_COOKIE["REMBR_USR_KEY"]);
        }
    }

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

    public static function uiGetInterface($interface_name, $field_set) {
        switch ($interface_name) {
        case "userx\\login":
            return array(
                "username" => __("Username:"),
                "password" => __("Password:"),
                "remember_login" => __("Remember Login"),
            );
        }
    }

    public function uiValidate($interface_name) {
        $err = array();
        switch ($interface_name) {
        case "userx\\login":
            if ($this->password === false)
                $err["password"] = __("The password confirmation did not match.");
            else if ($this->password == "")
                $err["password"] = __("You must enter a password.");
            break;
        }
        return $err;
    }
}
