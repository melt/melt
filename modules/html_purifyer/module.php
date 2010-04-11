<?php

namespace nanomvc\html_purifyer;

class HtmlPurifyerModule extends \nanomvc\Module {
    public static function beforeLoad() {
        // Include HTML Purifyer.
        $path = dirname(__FILE__) . "/htmlpurifier-4.0.0/library/HTMLPurifier.auto.php";
        require $path;
    }

    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>Wrapper module for HTML Purifyer.</b>"
        . "Wrapping HTML Purifyer - Standards-Compliant HTML Filtering "
        . "<a href=\"http://htmlpurifier.org/\">Visit htmlpurifier.org</a>.";
    }

    public static function getVersion() {
        return "1.0.0";
    }
}