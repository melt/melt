<?php namespace nmvc\userx;

abstract class RestrictedController extends \nmvc\Controller {
    public static function canAccess($action_name) {
        $cur_user = get_user();
        // Non logged in users are never authorized.
        if ($cur_user === null)
            return false;
        $cur_user_group = $cur_user->group_id;
        // User without groups have no permissions.
        if ($cur_user_group === null)
            return false;
        // Root groups can always login.
        if ($cur_user_group->root)
            return true;
        // Only let trough if permission exists.
        $ctrl_class_name = get_called_class();
        if (GroupPermissionModel::selectFirst("on_controller = " . strfy($ctrl_class_name)
        . " AND on_action = " . strfy($action_name) ." AND group_id = " . $cur_user_group->getID()) === null)
            return false;
        return true;
    }

    
    /** Only allows request if user is authorized. */
    public function beforeFilter($action_name) {
        $self = get_class();
        if (!$self::canAccess($action_name))
            \nmvc\request\show_xyz(403);
    }

    /**
     * Returns an array of all restricted controllers with their
     * restricted actions.
     * @return array
     */
    public static function getRestrictedActions() {
        static $restricted_controllers = false;
        if ($restricted_controllers !== false)
            return $restricted_controllers;
        $controllers = $restricted_controllers = array();
        foreach (scandir(APP_DIR . "/controllers/") as $contr_file_name)
            if ($contr_file_name[0] != ".")
                $controllers[] = "nmvc\\$contr_file_name\\" . \nmvc\string\underline_to_cased($contr_file_name);
        foreach ($controllers as $class_name) {
            if (!is_subclass_of($class_name, "RestrictedController"))
                continue;
            $restricted_actions = array();
            foreach (get_class_methods($class_name) as $action_name) {
                if ($action_name[0] == "_")
                    continue;
                $restricted_actions[] = $action_name;
            }
            $restricted_controllers[$class_name] = $restricted_actions;
        }
        return $restricted_controllers;
    }
}