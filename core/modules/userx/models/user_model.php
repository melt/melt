<?php namespace nmvc\userx;

abstract class UserModel_app_overrideable extends \nmvc\AppModel implements \nmvc\qmi\UserInterfaceProvider {
    /* This field is only used when config\MULTIPLE_GROUPS == false */
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');
    /* This field is only used when config\MULTIPLE_IDENTITIES == false */
    public $username = array(INDEXED, 'core\TextType', 128);

    public $password = 'userx\PasswordType';
    public $last_login_time = 'core\TimestampType';
    public $last_login_ip = 'core\IpAddressType';
    public $user_remember_key = array(INDEXED, 'core\PasswordType', 16);
    public $user_remember_key_expires = 'core\TimestampType';
    public $disabled = 'core\YesNoType';

    public $remember_login = array(VOLATILE, 'core\BooleanType');

    protected function initialize() {
        parent::initialize();
        if ($this->isVolatile()) {
            $this->username = @$_COOKIE["LAST_USER"];
            $this->remember_login = isset($_COOKIE["REMBR_USR_KEY"]);
        }
    }

    /**
     * Overridable callback event method.
     * Called before logging in a user trough "login_challenge".
     * If the function returns true, the login attempt will be succeed while
     * returning false forcefully fails the challenge.
     * @return boolean
     */
    public function loginChallengeFilter() {
        return true;
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
            return _("This account has been disabled.");
    }

    public static function uiGetInterface($interface_name, $field_set) {
        switch ($interface_name) {
        case "userx\\login":
            return array(
                "username" => _("Username:"),
                "password" => _("Password:"),
                "remember_login" => _("Remember Login"),
            );
        }
    }

    public function uiValidate($interface_name) {
        $err = array();
        if ($this->password === false)
            $err["password"] = _("The password confirmation did not match.");
        else if ($this->password == "")
            $err["password"] = _("You must enter a password.");
        return $err;
    }
}
