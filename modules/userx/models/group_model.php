<?php

namespace nmvc\userx;

/** A user group. */
class GroupModel extends \nmvc\Model {
    public $name = array('core\TextType');
    // Root groups have full permissions.
    public $root = array('core\YesNoType');
}