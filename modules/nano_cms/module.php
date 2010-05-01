<?php namespace nanomvc\nano_cms;

class NanoCmsModule extends \nanomvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>nanoCMS - A clean, slick CMS written for nanoMVC.</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "0.1.0";
    }

    public static function bcd_dynamic_pages() {
        $ret = array('nanomvc\nano_cms\DirectoryModel');
        if (!config\NO_DEFAULT_PAGES)
            $ret[] = 'nanomvc\nano_cms\PageModel';
        return $ret;
    }
    
    public static function bcd_admin_menu_items() {
        return array(
            "pageination" => array(
                "category" => "Pages",
                "icon" => "/static/mod/iconize/crystal/24x24/filesystems/file_doc.png",
                "weight" => -100,
                "paths" => array(
                    "/nano_cms/admin/pages" => "Edit Pages",
                )
            )
        );
    }
}