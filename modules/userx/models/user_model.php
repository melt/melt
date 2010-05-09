<?php

namespace nmvc\userx;

class UserModel extends \nmvc\Model {
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');
    public $username = 'core\TextType';
    public $password = 'userx\PasswordType';
    public $last_login_time = 'core\TimestampType';
    public $last_login_ip = 'core\IpAddressType';
    public $user_remember_key = 'core\PasswordType';
    public $data_id = array('core\SelectModelType', 'userx\UserDataModel');

    public function setPassword($new_password) {
        $this->password = hash_password($new_password);
    }

    public function validate() {
        if ($this->type("password")->getPasswordChangeStatus() === false)
            return array("password" => "Incorrect current password or new password confirm pair didn't match.");
        else
            return array();
    }
}
