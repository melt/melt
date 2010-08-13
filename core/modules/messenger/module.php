<?php namespace nmvc\messenger;

class MessengerModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        if (isset($_SESSION['next_flash']) && count($_SESSION['next_flash']) == 2) {
            \nmvc\Controller::registerBeforeFirstRenderEvent(function() {
                list($message, $status) = array_values($_SESSION['next_flash']);
                show_message($message, $status);
                unset($_SESSION['next_flash']);
            });
        }
    }
}