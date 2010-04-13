<?php namespace nanomvc\url_mapper;

class UrlMapperModule extends \nanomvc\Module {
    public static function catchRequest($url_tokens) {
        $alias_url_token = $url_tokens[0];
        $url_map = UrlMap::selectWhere("alias = " . strfy($alias_url_token));
        if ($url_map === false)
            return;
        Controller::invoke($url_map->invoke, false);
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