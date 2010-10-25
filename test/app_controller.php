<?php namespace nmvc;

/** Application specific controller. */
abstract class AppController extends Controller {
    public $layout = "/simple_layout";

    public function beforeFilter($action_name, $arguments) {
        // Any test has a maximum of 6 seconds to run before timing out.
        \set_time_limit(6);
    }

    public function beforeRender($action_name, $arguments) {}

    public function afterRender($action_name, $arguments) {}

    public static function rewriteRequest($path_tokens) {
        if (@$path_tokens[0] == "tg1_routing" && @$path_tokens[1] == "rewrite_me")
            return false;
        if (@$path_tokens[0] == "tg1_routing" && @$path_tokens[1] == "rewrite_you")
            return array("tg1_routing", "rewrite_me");
    }
}
