<?php

namespace nanomvc\jquery;

class JqueryModule extends \nanomvc\Module {
    public static function beforeRequestProcess() {
        // Auto include scripts.
        \nanomvc\View::render("/jquery/include_jquery", null, false, true);
        if (config\INCLUDE_JQUERY_CORNER)
            \nanomvc\View::render("/jquery/include_corner", null, false, true);
        if (config\INCLUDE_JQUERY_LIGHTBOX)
            \nanomvc\View::render("/jquery/include_lightbox", null, false, true);
        $ui_theme = config\JQUERY_UI_THEME;
        if (is_string($ui_theme) && strlen($ui_theme) > 0) {
            $controller = new \nanomvc\Controller();
            $controller->theme = $ui_theme;
            \nanomvc\View::render("/jquery/include_ui", $controller, false, true);
        }
    }

    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>Wrapper module for jquery and various jquery libraries</b>"
        . "Wrapping the following jquery libaries: <ul>"
        . "<li>jquery 1.4.2 - /static/mod/jquery/jquery.js</li>"
        . "<li>jquery-corner 2.09 - /static/mod/jquery/jquery-corner.js</li>"
        . "<li>jquery-lightbox 0.5 - /static/mod/jquery/jquery-lightbox.js</li>"
        . "<li>jquery-ui 1.8.custom.min - /static/mod/jquery/jquery-ui.js</li>"
        . "<li>jquery-ui-themes 1.7 - /static/mod/jquery/jquery-ui-themes/*</li>"
        . "</ul><a href=\"http://jquery.com/\">Visit jquery.com</a>.";
    }

    public static function getVersion() {
        return "1.0.0";
    }
}