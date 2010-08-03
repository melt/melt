<?php namespace nmvc\core;

class CoreModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        if (APP_IN_DEVELOPER_MODE)
            \nmvc\View::render("/core/devmode_includes", null, false, true);
    }
}