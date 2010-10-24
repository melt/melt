<?php namespace nmvc;

/**
 * Responsible for testing routing, controllers, views, layout, rewriting, etc.
 */
class Tg1RoutingController extends TestGroupController {
    public function _run() {
        parent::_run();
        $this->test("/tg1_routing/normal_action", "Test A");
        $this->test("/tg1_routing/return_test_action", "Test A");
        $this->test("/tg1_routing/nonexistant_view");
        $this->test("/tg1_routing/nonexistant_action", "404 - Page not found");
        $this->test("/tg1_routing/folder_view", "Folder\View");
        $this->test("/tg1_routing/parameterized", "404 - Page not found");
        $this->test("/tg1_routing/parameterized/foo", "foo, monkey, test_var");
        $this->test("/tg1_routing/parameterized/foo/bar", "foo, bar, test_var");
        $this->test("/tg1_routing/parameterized/foo/bar/test/x", "foo, bar/test/x, test_var");
        $this->test("/tg1_routing/invoking", "Folder\View");
        $this->test("/tg1_routing/invoking2", "404 - Page not found");
        $this->test("/tg1_routing/invoking2/foo", "foo, monkey, test_var");
        $this->test("/tg1_routing/invoking_bad/foo", "404 - Page not found");
        $this->test("/tg1_routing/invoking_bad", "");
        $this->test("/tg1_routing/_unreachable_externally", "404 - Page not found");
        $this->test("/tg1_routing/invoking3", "Test A");
        $this->test("/tg1_routing/test_layout_view_1", "[foo][bar]");
        $this->test("/tg1_routing/test_layout_view_2", "[foo_head][foo][foo_foot][baar]");
        $this->test("/tg1_routing/test_layout_view_3", "[foo_head][foo_head][bar][foo][foo_foot][foo_foot][bar]");
        $this->test("/tg1_routing/rewrite_me", "404 - Page not found");
        $this->test("/tg1_routing/rewrite_you", "rewritten");
        $this->complete();
    }

    public $test_var = "test_var";

    public function normal_action() {}

    public function return_test_action() {
        return "/tg1_routing/normal_action";
    }

    public function nonexistant_view() {
        return "/tg1_routing/test_0/nonexistant";
    }

    public function folder_view() {
        return "/tg1_routing/folder_view/test";
    }

    public function parameterized($foo, $bar = "monkey") {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function invoking() {
        $this->assert($this->invoke("folder_view"), true);
    }

    public function invoking2($foo) {
        $this->assert($this->invoke("parameterized", array($foo)), true);
    }

    public function invoking_bad() {
        $this->assert($this->invoke("parameterized"), false);
    }

    public function _unreachable_externally() {
        return "/tg1_routing/normal_action";
    }

    public function invoking3() {
        $this->assert($this->invoke("_unreachable_externally"), true);
    }

    public function test_layout_view_1() {
        $this->layout = "/tg1_routing/test_layout";
    }
    
    public function test_layout_view_2() {
        $this->layout = "/tg1_routing/test_layout";
    }
    
    public function test_layout_view_3() {
        $this->layout = "/tg1_routing/test_layout";
    }

    public function rewrite_me() {
        $this->assert(REQ_URL, "/tg1_routing/rewrite_you");
        return;
    }
}
