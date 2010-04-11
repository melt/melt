<?php

namespace nanomvc\tinymce;

class TinymceModule extends \nanomvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>TinyMCE WYSIWYG Editor</b>"
        . "TinyMCE 3.3.2 - A javascript WYSIWYG Editor"
        . "<a href=\"http://tinymce.moxiecode.com/\">Visit TinyMCE</a>.";
    }

    public static function getVersion() {
        return "1.0.0";
    }
}