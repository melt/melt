<?php namespace melt\js;

class JsModule extends \melt\CoreModule {
    public static function beforeRequestProcess() {
        // Auto include required scripts that core needs.
        \melt\View::render("/js/include_jquery", null, false, true);
        \melt\View::render("/js/include_sprintf", null, false, true);
        // Auto include optional/configurable scripts.
        $ui_theme = config\JQUERY_UI_THEME;
        if (is_string($ui_theme) && strlen($ui_theme) > 0)
            \melt\View::render("/js/include_ui", array("theme" => $ui_theme), false, true);
        if (config\INCLUDE_JQUERY_ALERTS)
            \melt\View::render("/js/include_alerts", null, false, true);
        if (config\INCLUDE_JQUERY_CORNER)
            \melt\View::render("/js/include_corner", null, false, true);
        if (config\INCLUDE_JQUERY_LIGHTBOX)
            \melt\View::render("/js/include_lightbox", null, false, true);
        if (config\INCLUDE_JQUERY_DATATABLES)
            \melt\View::render("/js/include_datatables", null, false, true);
        if (config\INCLUDE_JQUERY_AUTOCOMPLETE)
            \melt\View::render("/js/include_autocomplete", null, false, true);
        if (config\INCLUDE_JQUERY_AUTORESIZE)
            \melt\View::render("/js/include_autoresize", null, false, true);
        if (config\INCLUDE_JQUERY_COOKIE)
            \melt\View::render("/js/include_cookie", null, false, true);
        if (config\INCLUDE_JQUERY_FORM)
            \melt\View::render("/js/include_form", null, false, true);
        if (config\INCLUDE_JQUERY_RESIZE)
            \melt\View::render("/js/include_resize", null, false, true);
        if (config\INCLUDE_LESS_CSS)
            \melt\View::render("/js/include_less", null, false, true);
    }

    public static function getAuthor() {
        return "Module maintained by Hannes Landeholm, Melt Software AB. This module is a compilation of code from various authors listed in getInfo(), which is not related to, nor endorse this software in any way.";
    }

    public static function getInfo() {
        return "<b>Wrapper module for jquery and various js libraries</b>"
        . "Wrapping the following libaries: <ul>"
        . "<li>jquery 1.6.1 - http://jquery.com/</li>"
        . "<li>jquery-corner 2.09 - http://jquery.malsup.com/corner/</li>"
        . "<li>jquery-lightbox 0.5 - http://leandrovieira.com/projects/js/lightbox/</li>"
        . "<li>jquery-ui 1.8.13 - http://jqueryui.com/</li>"
        . "<li>jquery-ui-themes 1.7 - http://jqueryui.com/</li>"
        . "<li>jquery-alerts 1.1 - http://abeautifulsite.net/blog/2008/12/jquery-alert-dialogs/</li>"
        . "<li>jquery-tree 1.0rc2 - http://www.jstree.com/</li>"
        . "<li>jquery-form 2.81 - http://jquery.malsup.com/form/</li>"
        . "<li>jquery-autocomplete 1.1 - http://bassistance.de/jquery-plugins/jquery-plugin-autocomplete/</li>"
        . "<li>less css 1.1.3 - http://lesscss.org/</li>"
        . "<li>sprintf() for JavaScript 0.7-beta1 - http://www.diveintojavascript.com/projects/javascript-sprintf</li>"
        . "</ul>";
    }
}