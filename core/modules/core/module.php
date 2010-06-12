<?php namespace nmvc\core;

class CoreModule extends \nmvc\CoreModule {

    public static function beforeRender() {
        if (APP_IN_DEVELOPER_MODE)
            \nmvc\View::render("/core/include_pretty_print", null, false, true);
    }

}