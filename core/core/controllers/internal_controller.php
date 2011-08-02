<?php namespace melt\core;

/**
 * Special controller that is not filtered by developer mode.
 * @internal
 */
abstract class InternalController extends \melt\AppController {

    // De-prototype any application defined callback logic that can
    // break the request for any internal controller.
    
    public function beforeFilter($action_name, $arguments) {}
    public function beforeRender($action_name, $arguments) {}
    public function afterRender($action_name, $arguments) {}
    
}