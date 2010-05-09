<?php namespace nmvc\userx;

/** That an instance of this model exists implies that this group has permission to access this controller. */
class GroupPermissionModel extends \nmvc\Model {
    public $on_controller = array('core\TextType');
    public $on_action = array('core\TextType');
    public $group_id = array('core\SelectModelType', 'userx\GroupModel');
}