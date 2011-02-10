<?php namespace nmvc\userx;

abstract class RestrictedController extends \nmvc\AppController {

    private static function getGroupPermitted($group, $controller_class_name, $invoke_data, &$special_permissions) {
        // Root groups can always login.
        if ($group !== null && $group->root)
            return true;
        // Get permission and evaluate it (and set to default if not specified).
        $permission = GroupPermissionModel::select()->byKey(array(
            "controller" => $controller_class_name,
            "group_id" => isset($group)? $group->id: 0
        ))->first();
        if ($permission === null)
            $permission = $controller_class_name::getDefaultPermission($group);
        else
            $permission = $permission->permission;
        if (strcasecmp($permission, "Allow") === 0)
            return true;
        else if (strcasecmp($permission, "Deny") === 0)
            return false;
        // Special permission found, evaluate them.
        $special_permissions = $permission;
        return $controller_class_name::canAccessAsWhere($special_permissions, $invoke_data->getActionName(), $invoke_data->getArguments());
    }

    /**
     * Returns true if given group can use this controller.
     * If this group uses special permissions for this controller the
     * action and arguments will also be evaluated.
     * @param mixed $local_url String or core\InvokeData
     * @param mixed $group_or_user NULL (for guest), userx\UserModel
     * or userx\GroupModel. If passing user, that users group will be used.
     * @param string $special_permissions If passed, will be set to
     * the special permissions the group uses or NULL.
     */
    public static function canAccess($local_url, $group_or_user, &$special_permissions = null) {
        $special_permissions = null;
        if (is($local_url, 'nmvc\core\InvokeData')) {
            $invoke_data = $local_url;
        } else {
            // Get controller, action and arguments from path.
            $invoke_data = \nmvc\Controller::pathToInvokeData($local_url);
            if ($invoke_data === false)
                return true;
        }
        $controller_class_name = $invoke_data->getControllerClass();
        // If not a restricted controllern, then access is possible.
        if (!is($controller_class_name, __CLASS__))
            return true;
        // Access now depends on group.
        if ($group_or_user === null)
            return self::getGroupPermitted(null, $controller_class_name, $invoke_data, $special_permissions);
        else if ($group_or_user instanceof UserModel) {
            if (config\MULTIPLE_GROUPS) {
                // Iterate trough all group membership.
                // If one group is permitted then
                // user access is granted per definition.
                $user = $group_or_user;
                static $cached_user_groups = array();
                if (!\array_key_exists($user->id, $cached_user_groups))
                    $cached_user_groups[$user->id] = UserGroupModel::selectChildren($user);
                foreach ($cached_user_groups[$user->id] as $user_group) {
                    if (self::getGroupPermitted($user_group->group, $controller_class_name, $invoke_data, $special_permissions))
                        return true;
                }
                return self::getGroupPermitted(null, $controller_class_name, $invoke_data, $special_permissions);
            } else
                return self::getGroupPermitted($group_or_user->group, $controller_class_name, $invoke_data, $special_permissions);
        } else if ($group_or_user instanceof GroupModel)
            return self::getGroupPermitted($group_or_user, $controller_class_name, $invoke_data, $special_permissions);
        else
            trigger_error(__METHOD__ . " got unexpected \$group_or_user: " . \gettype($group_or_user), \E_USER_ERROR);
    }

    /**
     * Return default permission for the given group and controller.
     * The group can be null which indicates a groupless user or guest.
     * Returns 'Deny' by default.
     * @param nmvc\userx\GroupModel $group Group to evaluate.
     */
    public static function getDefaultPermission(GroupModel $group = null) {
        return "Deny";
    }

    /**
     * Returns the permissions this controller should support except
     * Allow and Deny.
     * @return array
     */
    public static function getSupportedPermissions() {
        return array();
    }

    /**
     * Should return true if this permission is allowed for this action
     * and arguments on this controller.
     * @param string $special_permission Special permissions
     * @param string $action
     * @param array $arguments
     * @return boolean
     */
    protected static function canAccessAsWhere($special_permission, $action, $arguments) {
        return true;
    }
    
    /**
     * Returns a special permission for this invoke or NULL if this invoke
     * has no special permissions configured or tripped.
     */
    protected function getSpecialPermissionsForThisInvoke() {
        return $this->special_permissions;
    }

    private $special_permissions = null;

    /** Only allows request if user is authorized. */
    public function beforeFilter($action_name, $parameters) {
        if (!self::canAccess(\nmvc\Controller::getCurrentlyInvoked(), get_user(), $this->special_permissions))
            deny();
        parent::beforeFilter($action_name, $parameters);
    }

    /**
     * Returns an array of all restricted controllers with their possible
     * permission levels.
     * @return array
     */
    public static function getAllControllerPermissions() {
        static $restricted_controllers = false;
        if ($restricted_controllers !== false)
            return $restricted_controllers;
        $controllers = $restricted_controllers = array();
        foreach (scandir(APP_DIR . "/controllers/") as $contr_file_name)
            if ($contr_file_name[0] != ".")
                $controllers[] = "nmvc\\" . \nmvc\string\underline_to_cased(substr($contr_file_name, 0, -4));
        foreach ($controllers as $class_name) {
            if (\nmvc\core\is_abstract($class_name))
                continue;
            if (!is_subclass_of($class_name, __CLASS__))
                continue;
            $permissions = array_merge(array("Allow", "Deny"), $class_name::getSupportedPermissions());
            $restricted_controllers[$class_name] = $permissions;
        }
        return $restricted_controllers;
    }
}