<?php namespace melt\request;

class InfoController extends \melt\AppController {
    function _show($topic, $body) {
        $this->topic = $topic;
        $this->body = $body;
        return "/request/info";
    }
}