<?php namespace nanomvc\nano_cms;

class DirectoryModel extends SiteNodeModel {
    /** Path url_mapper will invoke and pass ID to. */
    static function getInvokePath() {
        return "/nano_cms/view/_dir";
    }

    /** Friendly name of node type to display to user. */
    static function getPageTypeName() {
        return "Directory";
    }

    static function getAdminViewPath() {
        return "/nano_cms/admin/dir";
    }

    public static function getJsTreeIcon() {
        return "/static/mod/iconize/crystal/16x16/filesystems/folder.png";
    }

    public function getURL($what_url = null) {
        if ($what_url == "admin")
            return parent::getURL("admin");
        else
            return null;
    }
}