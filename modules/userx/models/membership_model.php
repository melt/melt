<?php

namespace nanomvc\userx;

/**
 * A user membership in a certain group.
 */
class MembershipModel extends \nanomvc\Model {
    public $user_id = array('core\SelectModelType', 'userx\UserModel');
    public $of_group = 'userx\GroupSelectorType';
}