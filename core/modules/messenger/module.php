<?php namespace melt\messenger;

class MessengerModule extends \melt\CoreModule {
    public static function beforeRequestProcess() {
        if (isset($_SESSION['next_flash']) && count($_SESSION['next_flash']) == 2) {
            \melt\Controller::registerBeforeFirstRenderEvent(function() {
                list($message, $status) = array_values($_SESSION['next_flash']);
                show_message($message, $status);
                unset($_SESSION['next_flash']);
            });
        }
    }
}