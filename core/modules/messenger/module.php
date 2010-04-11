<?php

namespace nanomvc\messenger;

class MessengerModule extends \nanomvc\CoreModule {
    public static function beforeRequestProcess() {
        if (isset($_SESSION['next_flash'])) {
            list($message, $status) = $_SESSION['next_flash'];
            showMessage($message, $status);
            unset($_SESSION['next_flash']);
        }
    }
}