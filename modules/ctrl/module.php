<?php namespace nmvc\ctrl;

class CtrlModule extends \nmvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>Ctrl - A robust site administration implementation.</b>"
        . "Developers: Note that this module uses <a href=\"http://devkick.com/lab/tripoli/\">tripoli</a>."
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "0.1.0";
    }


    public static function bcd_admin_menu_items() {
        return array(
            "misc" => array(
                "category" => "Miscellaneous",
                "weight" => 100,
                "icon" => "/static/mod/iconize/crystal/24x24/apps/miscellaneous.png",
                "paths" => array(
                    "/ctrl/admin/settings" => "Ctrl Settings",
                    "/ctrl/admin/about" => "About Ctrl",
                )
            )
        );
    }
}