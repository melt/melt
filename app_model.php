<?php namespace nmvc;

/** Application specific model. */
abstract class AppModel extends Model {
    // Some useful callback/event methods.
    // Refer to the manual for more information.
    
    public function beforeStore($is_linked) { }

    public function afterStore($was_linked) { }

    public function beforeUnlink() { }

    public function afterUnlink() { }

    public function disconnectCallback($pointer_name) { }

    public function accessing() { }

    public function initialize() { }

    public function validate() {
        return array();
    }
}
