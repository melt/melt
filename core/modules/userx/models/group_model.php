<?php namespace melt\userx;

/** A user group. */
abstract class GroupModel_app_overrideable extends \melt\AppModel {
    public $name = array('core\TextType');
    // Root groups have full permissions.
    public $root = array('core\YesNoType');
}