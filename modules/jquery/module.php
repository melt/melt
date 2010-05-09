<?php

namespace nmvc\jquery;

class JqueryModule extends \nmvc\Module {
    public static function beforeRequestProcess() {
        // Auto include scripts.
        \nmvc\View::render("/jquery/include_jquery", null, false, true);
        $ui_theme = config\JQUERY_UI_THEME;
        if (is_string($ui_theme) && strlen($ui_theme) > 0) {
            $controller = new \nmvc\Controller();
            $controller->theme = $ui_theme;
            \nmvc\View::render("/jquery/include_ui", $controller, false, true);
        }
        if (config\INCLUDE_JQUERY_CORNER)
            \nmvc\View::render("/jquery/include_corner", null, false, true);
        if (config\INCLUDE_JQUERY_LIGHTBOX)
            \nmvc\View::render("/jquery/include_lightbox", null, false, true);
        if (config\INCLUDE_JQUERY_TREE)
            \nmvc\View::render("/jquery/include_tree", null, false, true);
        if (config\INCLUDE_JQUERY_DATATABLES)
            \nmvc\View::render("/jquery/include_datatables", null, false, true);
    }

    public static function getAuthor() {
        return "Wrapper maintained by Hannes Landeholm, Media People Sverige AB";
    }

    public static function getInfo() {
        return "<b>Wrapper module for jquery and various jquery libraries</b>"
        . "Wrapping the following jquery libaries: <ul>"
        . "<li>jquery 1.4.2 - http://jquery.com/</li>"
        . "<li>jquery-corner 2.09 - http://jquery.malsup.com/corner/</li>"
        . "<li>jquery-lightbox 0.5 - http://leandrovieira.com/projects/jquery/lightbox/</li>"
        . "<li>jquery-ui 1.8.custom.min - http://jqueryui.com/</li>"
        . "<li>jquery-ui-themes 1.7 - http://jqueryui.com/</li>"
        . "<li>jquery-tree 0.9.9a2 - http://www.jstree.com/</li>"
        . "</ul>";
    }

    public static function getVersion() {
        return "1.0.0";
    }
}