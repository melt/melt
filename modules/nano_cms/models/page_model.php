<?php namespace nmvc\nano_cms;

class PageModel extends SiteNodeModel {
    public $content = 'tinymce\WysiwygType';

    /** Path url_mapper will invoke and pass ID to. */
    static function getInvokePath() {
        return "/nano_cms/view/_page";
    }

    /** Friendly name of node type to display to user. */
    public static function getPageTypeName() {
        return "Page";
    }

    static function getAdminViewPath() {
        return "/nano_cms/admin/page";
    }
    
    public static function getJsTreeIcon() {
        return "/static/mod/iconize/crystal/16x16/mimetypes/document.png";
    }

    public function initialize() {
        // Configure the wysiwyg.
        \nmvc\ctrl\configure_wysiwyg($this->type("content"));
    }
}

