<?php namespace nmvc\ctrl;

class CtrlSettingsModel extends \nmvc\SingletonModel {
    public $wysiwyg_type = array(
        'core\SelectType',
        "options" => array(
            "simple" => "Simple",
            "advanced" => "Advanced",
        )
    );
}
