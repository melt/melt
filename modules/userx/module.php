<?php

namespace nmvc\userx;

class UserxModule extends \nmvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>userx - flexible and secure user rights management</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "1.0.1";
    }
}