<?php namespace nmvc\userx;

/** That an instance of this model exists implies that this group has permission to access this controller. */
abstract class GroupPermissionModel_app_overrideable extends \nmvc\AppModel {
    /** If this instance does not exist or this type is unknown,
     * permission is denied. */
    public $permission = array('core\TextType');
    /** Class name of controller. */
    public $controller = array('core\TextType');
    /** Group id or 0 for guests. */
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');

    public static function getPermission($controller, GroupModel $group) {
        $permission = self::selectFirst("controller = " . strfy($controller) . " AND group_id = " . $group->id);
        if ($permission === null)
            return "Deny";
        return $permission->permission;
    }
}