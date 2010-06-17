<?php namespace nmvc\request;

class InfoController extends \nmvc\AppController {
    function _show($topic, $body) {
        $this->topic = $topic;
        $this->body = $body;
        return "/request/info";
    }
}