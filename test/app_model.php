<?php namespace nmvc;

/** Application specific model. */
abstract class AppModel extends Model {
    // Some useful callback/event methods.
    // Refer to the manual for more information.

    protected function initialize() { }

    protected function afterLoad() { }
    
    protected function beforeStore($is_linked) { }

    protected function afterStore($was_linked) { }

    protected function beforeUnlink() { }

    protected function afterUnlink() { }

    protected function disconnectCallback($pointer_name) { }

    public function validate() {
        return array();
    }
}
