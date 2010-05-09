<?php

namespace nmvc\tinymce;

class TinymceModule extends \nmvc\Module {
    public static function getAuthor() {
        return "Hannes Landeholm, Media People Sverige AB";
    }

    public static function getInfo() {
        return "<b>TinyMCE WYSIWYG Editor</b>"
        . "TinyMCE - A javascript WYSIWYG Editor"
        . "<a href=\"http://tinymce.moxiecode.com/\">Visit TinyMCE</a>.";
    }

    public static function getVersion() {
        return "1.0.1";
    }
}