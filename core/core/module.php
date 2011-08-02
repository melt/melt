<?php namespace melt\core;

class CoreModule extends \melt\CoreModule {
    public static function beforeRequestProcess() {
        if (APP_IN_DEVELOPER_MODE)
            \melt\View::render("/core/devmode_includes", null, false, true);
    }
}