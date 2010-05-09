<?php namespace nmvc\messenger;

class MessengerModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        if (isset($_SESSION['next_flash'])) {
            list($message, $status) = $_SESSION['next_flash'];
            show_message($message, $status);
            unset($_SESSION['next_flash']);
        }
    }
}