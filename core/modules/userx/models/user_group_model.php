<?php namespace nmvc\userx;

/**
 * Represents a user group relation.
 * Only used when MULTIPLE_IDENTITIES == true
 */
abstract class UserGroupModel_app_overrideable extends \nmvc\AppModel {
    public $user_id = array('core\PointerType', 'userx\UserModel', 'CASCADE');
    public $group_id = array('core\PointerType', 'userx\GroupModel', 'CASCADE');
}