<?php

namespace nanomvc\userx;

class UserModel extends \nanomvc\Model {
    public $username = 'core\TextType';
    public $password = 'core\PasswordType';
    public $last_login_time = 'core\TimestampType';
    public $last_login_ip = 'core\IpAddressType';
    public $membership_id = array('core\SelectModelType', 'userx\UserModel');
    public $data_id = array('core\SelectModelType', 'userx\UserDataModel');

    /**
     * Checks if this user is a member of the given group.
     * Use this function for all permission management.
     * @param mixed $group Either a group ID or a string group.
     */
    public function isMemberOf($group) {
        // Checks if user if member of root account.
        if (MembershipModel::count("user_id = " . $this->getID() . " AND group = 1") != 0)
            return true;
        // Convert group name to id.
        if (is_string($group)) {
            $group = array_search($group, config\getApplicationUserGroups());
            if ($group === false)
                return false;
        }
        $group = intval($group);
        return MembershipModel::count("user_id = " . $this->getID() . " AND group = $group") != 0;
    }
}
