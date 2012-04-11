<?php namespace melt\js;

class JsModule extends \melt\CoreModule {    
    public static function beforeLayoutRender() {
        // Auto include required scripts that core needs.
        \melt\View::render("/js/include_jquery", null, false, true);
        \melt\View::render("/js/include_sprintf", null, false, true);
        \melt\View::render("/js/include_cookie", null, false, true);
        // Auto include optional/configurable scripts.
        if (config\INCLUDE_JQUERY_DATATABLES)
            \melt\View::render("/js/include_datatables", null, false, true);
        if (config\INCLUDE_JQUERY_AUTOCOMPLETE)
            \melt\View::render("/js/include_autocomplete", null, false, true);
        if (config\INCLUDE_JQUERY_AUTORESIZE)
            \melt\View::render("/js/include_autoresize", null, false, true);
        if (config\INCLUDE_JQUERY_FORM)
            \melt\View::render("/js/include_form", null, false, true);
        if (config\INCLUDE_LESS_CSS)
            \melt\View::render("/js/include_less", null, false, true);
        if (config\INCLUDE_TWITTER_BOOTSTRAP)
            \melt\View::render("/js/include_bootstrap", null, false, true);
        if (config\INCLUDE_TWITTER_BOOTSTRAP_BOOTBOX)
            \melt\View::render("/js/include_bootbox", null, false, true);
        if (config\INCLUDE_TWITTER_BOOTSTRAP_IMAGEGALLERY)
            \melt\View::render("/js/include_imagegallery", null, false, true);
    }

    public static function getAuthor() {
        return "Module maintained by Melt Software AB. This module is a compilation of code from various authors listed in getInfo(), which is not related to, nor endorse this software in any way.";
    }

    public static function getInfo() {
        return "<b>Wrapper module for jquery and various js libraries</b>"
        . "Wrapping the following libaries: <ul>"
        . "<li>jquery 1.7.1 - http://jquery.com/</li>"
        . "<li>sprintf() for JavaScript 0.7-beta1 - http://www.diveintojavascript.com/projects/javascript-sprintf</li>"
        . "<li>jquery-cookie - https://github.com/carhartl/jquery-cookie/</li>"
        . "<li>jquery-datatables 1.9.0 - http://datatables.net/</li>"
        . "<li>jquery-autocomplete 1.1 - http://bassistance.de/jquery-plugins/jquery-plugin-autocomplete/</li>"
        . "<li>jquery-autoresize 1.14 - https://github.com/padolsey/jQuery.fn.autoResize</li>"
        . "<li>jquery-form 3.0.2 - http://jquery.malsup.com/form/</li>"
        . "<li>less css 1.3.0 - http://lesscss.org/</li>"
        . "<li>twitter-bootstrap 2.0.2 - http://twitter.github.com/bootstrap</li>"
        . "<li>twitter-bootstrap-bootbox 2.1.2 - https://github.com/makeusabrew/bootbox</li>"
        . "<li>twitter-bootstrap-imagegallery 2.2.2 - https://github.com/blueimp/Bootstrap-Image-Gallery</li>"
        . "</ul>";
    }
}