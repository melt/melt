<?php namespace melt\core;

class CoreModule extends \melt\CoreModule {
    public static function beforeLayoutRender() {
        if (APP_IN_DEVELOPER_MODE && config\DISPLAY_DEVMODE_NOTICE)
            \melt\View::render("/core/devmode_includes", null, false, true);
    }
}