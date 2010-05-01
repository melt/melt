<?php namespace nanomvc\ctrl;

class CtrlSettingsModel extends \nanomvc\SingletonModel {
    public $wysiwyg_type = array(
        'core\SelectType',
        "options" => array(
            "simple" => "Simple",
            "advanced" => "Advanced",
        )
    );
}
