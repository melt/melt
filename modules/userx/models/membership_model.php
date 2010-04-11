<?php

namespace nanomvc\userx;

/**
 * A user membership in a certain group.
 */
class MembershipModel extends \nanomvc\Model {
    public $user_id = 'core\Select,userx\User';
    public $of_group = 'userx\GroupSelector';
}