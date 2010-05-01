<?php namespace nanomvc\iconize;

class IconizeModule extends \nanomvc\Module {
    public static function getAuthor() {
        return "Wrapper by Hannes Landeholm, Media People Sverige AB";
    }

    public static function getInfo() {
        return "<b>Iconize - A large collection of icons.</b>"
        . "This module contains several good looking icon sets made by "
        . "various artists and licensed with either under <a href=\"http://www.gnu.org/licenses/lgpl.html\">LGPL</a>, "
        . "<a href=\"http://creativecommons.org/licenses/by/2.5/\">Creative Commons</a> or to the <a href=\"http://creativecommons.org/licenses/publicdomain/\">public domain</a>."
        . "<ul>"
        . "<li>crystal - <a href=\"http://www.everaldo.com/crystal/\">Crystal Project Icons</a> - LGPL</li>"
        . "<li>fileicons - <a href=\"http://www.everaldo.com/crystal/\">Crystal Project Icons</a> - Public Domain</li>"
        . "<li>flag/mini/silk - <a href=\"http://www.everaldo.com/crystal/\">Crystal Project Icons</a> - Public Domain</li>"
        . "<li>nuvola - <a href=\"http://www.icon-king.com/projects/nuvola/\">David Vignoni</a> - GNU LGPL 2.1</li>"
        . "</ul>";
    }

    public static function getVersion() {
        return "1.0.0";
    }
}