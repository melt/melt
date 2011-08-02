<?php namespace melt\userx;

/** That an instance of this model exists implies that this group has permission to access this controller. */
abstract class GroupPermissionModel_app_overrideable extends \melt\AppModel {
    protected $index_groups = array(
        "controller" => array("group_id" => INDEXED_UNIQUE),
    );

    /** If this instance does not exist or this type is unknown,
     * permission is denied. */
    public $permission = array('core\TextType', 32);
    /** Class name of controller. */
    public $controller = array('core\TextType', 128);
    /** Group id or 0 for guests. */
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');

    public static function getPermission($controller, GroupModel $group) {
        /*$permission = self::select()->where("controller")->is($controller)
        ->and("group_id")->is($group->id)->first();*/
        $permission = self::select()->byKey(array("controller" => $controller, "group" => $group))->first();
        if ($permission === null)
            return "Deny";
        return $permission->permission;
    }
}