<?php

namespace nanomvc\thready;

class ThreadyModule extends \nanomvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>thready - Flexible public comment threader</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "0.1.0";
    }
}