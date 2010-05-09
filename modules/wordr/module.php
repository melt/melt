<?php

namespace nmvc\wordr;

class WordrModule extends \nmvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>wordr - Flexible and functional blogging module</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "0.1.0";
    }
}