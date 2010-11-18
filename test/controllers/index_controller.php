<?php namespace nmvc;

class IndexController extends AppController {
    public $layout = "/html/xhtml1.1";

    /** Runs nanoMVC self tests. */
    function index() {
        $this->started = time();
        Tg1RoutingController::invoke("_run");
        Tg2DatabaseController::invoke("_run");
        Tg3TypesController::invoke("_run");
        Tg4StringController::invoke("_run");
    }
}