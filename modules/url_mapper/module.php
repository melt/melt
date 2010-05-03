<?php namespace nanomvc\url_mapper;

class UrlMapperModule extends \nanomvc\Module {
    /** @var UrlMapModel The url mapped model that matched this request. */
    public static $url_map = null;

    public static function catchRequest($url_tokens) {
        $alias_url = implode("/", $url_tokens);
        $url_map = UrlMapModel::selectFirst("url_alias = " . strfy($alias_url));
        if ($url_map === null)
            return;
        self::$url_map = $url_map;
        \nanomvc\Controller::invoke($url_map->invoke . $url_map->getID(), false);
        exit;
    }

    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Media People Sverige AB, ©$year";
    }

    public static function getInfo() {
        return "<b>URL Mapper - Maps certain URL's.</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "0.1.0";
    }
}