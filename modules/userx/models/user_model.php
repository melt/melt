<?php

namespace nanomvc\userx;

class UserModel extends \nanomvc\Model {
    public $username = 'core\Text';
    public $password = 'core\Password';
    public $last_login_time = 'core\Timestamp';
    public $last_login_ip = 'core\IpAddress';
    public $membership_id = 'core\Select,userx\User';
    public $data_id = 'core\Select,userx\UserData';

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
