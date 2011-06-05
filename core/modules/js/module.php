<?php namespace nmvc\js;

class JqueryModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        // Auto include scripts.
        \nmvc\View::render("/js/include_jquery", null, false, true);
        $ui_theme = config\JQUERY_UI_THEME;
        if (is_string($ui_theme) && strlen($ui_theme) > 0)
            \nmvc\View::render("/js/include_ui", array("theme" => $ui_theme), false, true);
        if (config\INCLUDE_JQUERY_ALERTS)
            \nmvc\View::render("/js/include_alerts", null, false, true);
        if (config\INCLUDE_JQUERY_CORNER)
            \nmvc\View::render("/js/include_corner", null, false, true);
        if (config\INCLUDE_JQUERY_LIGHTBOX)
            \nmvc\View::render("/js/include_lightbox", null, false, true);
        if (config\INCLUDE_JQUERY_DATATABLES)
            \nmvc\View::render("/js/include_datatables", null, false, true);
        if (config\INCLUDE_JQUERY_AUTOCOMPLETE)
            \nmvc\View::render("/js/include_autocomplete", null, false, true);
        if (config\INCLUDE_JQUERY_AUTORESIZE)
            \nmvc\View::render("/js/include_autoresize", null, false, true);
        if (config\INCLUDE_JQUERY_COOKIE)
            \nmvc\View::render("/js/include_cookie", null, false, true);
        if (config\INCLUDE_JQUERY_FORM)
            \nmvc\View::render("/js/include_form", null, false, true);
        if (config\INCLUDE_JQUERY_RESIZE)
            \nmvc\View::render("/js/include_resize", null, false, true);
        if (config\INCLUDE_JQUERY_HOTKEYS)
            \nmvc\View::render("/js/include_hotkeys", null, false, true);
        if (config\INCLUDE_JQUERY_JSTREE) {
            \nmvc\View::render("/js/include_jstree", null, false, true);
            if (!config\INCLUDE_JQUERY_HOTKEYS || !config\INCLUDE_JQUERY_COOKIE)
                trigger_error("Dependancy error in jquery configuration: JsTree plugin requires cookie and hotkeys to be enabled.", \E_USER_ERROR);
        }
        if (config\INCLUDE_LESS_CSS)
            \nmvc\View::render("/less/include_less", null, false, true);
    }

    public static function getAuthor() {
        return "Module maintained by Hannes Landeholm, Omnicloud AB. This module is a compilation of code from various authors listed in getInfo(), which is not related to, nor endorse this software in any way.";
    }

    public static function getInfo() {
        return "<b>Wrapper module for jquery and various js libraries</b>"
        . "Wrapping the following libaries: <ul>"
        . "<li>jquery 1.4.2 - http://jquery.com/</li>"
        . "<li>jquery-corner 2.09 - http://jquery.malsup.com/corner/</li>"
        . "<li>jquery-lightbox 0.5 - http://leandrovieira.com/projects/js/lightbox/</li>"
        . "<li>jquery-ui 1.8.6 - http://jqueryui.com/</li>"
        . "<li>jquery-ui-themes 1.7 - http://jqueryui.com/</li>"
        . "<li>jquery-alerts 1.1 - http://abeautifulsite.net/blog/2008/12/jquery-alert-dialogs/</li>"
        . "<li>jquery-tree 1.0rc2 - http://www.jstree.com/</li>"
        . "<li>jquery-form 2.47 - http://malsup.com/js/form/</li>"
        . "<li>jquery-autocomplete 1.1 - http://bassistance.de/jquery-plugins/jquery-plugin-autocomplete/</li>"
        . "<li>jquery-hotkeys 0.7.9 - http://code.google.com/p/js-hotkeys/</li>"
        . "<li>less css 1.1.3 - http://lesscss.org/</li>"
        . "</ul>";
    }
}