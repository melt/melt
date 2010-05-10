<?php namespace nmvc;

/** Application specific controller. */
abstract class AppController extends Controller {
    // The layout your controllers use by default.
    public $layout = "/html/xhtml1.1";

    /**
     * This function is executed before every action in the controller.
     * It's a handy place to check for an active session or
     * inspect user permissions.
     * @param $action_name Action name about to be called. This function may
     * be called with additional parameters.
     */
    public function beforeFilter($action_name) {}

    /**
     * Called after controller action logic, but before the view is rendered.
     */
    public function beforeRender() {}

    /**
     * Called after every controller action, and after rendering is complete.
     * This is the last controller method to run.
     */
    public function afterRender() {}
}
