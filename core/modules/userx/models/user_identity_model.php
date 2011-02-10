<?php namespace nmvc\userx;

/**
 * Represents a single user identity.
 * Only used when MULTIPLE_IDENTITIES == true
 */
abstract class UserIdentityModel_app_overrideable extends \nmvc\AppModel {
    public $user_id = array('core\PointerType', 'userx\UserModel', 'CASCADE');
    public $username = array(INDEXED_UNIQUE, 'core\TextType', 128);
}
