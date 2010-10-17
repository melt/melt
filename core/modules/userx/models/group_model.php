<?php namespace nmvc\userx;

/** A user group. */
abstract class GroupModel_app_overrideable extends \nmvc\AppModel {
    public $name = array('core\TextType');
    // Root groups have full permissions.
    public $root = array('core\YesNoType');
}