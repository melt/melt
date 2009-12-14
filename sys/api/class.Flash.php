<?php

class Flash {
    public static $flash_message = null;
    public static $flash_status = null;

    /**
    * @desc Returns an array of flasher variable references that can be used in template.
    */
    public static function getFlashRef() {
        if (isset($_SESSION['next_flash']) && is_array($_SESSION['next_flash'])) {
            list(self::$flash_message, self::$flash_status) = $_SESSION['next_flash'];
            unset($_SESSION['next_flash']);
        }
        return array(
            'message' => &self::$flash_message,
            'status' => &self::$flash_status,
        );
    }

    /**
    * @desc Flashes a message inline in the current request.
    */
    public static function doFlash($message, $status = FLASH_BAD) {
        self::$flash_message = $message;
        self::$flash_status = $status;
    }

    /**
    * @desc Redirects and then flashes a message.
    */
    public static function doFlashRedirect($url, $message, $status = FLASH_BAD) {
        $_SESSION['next_flash'] = array($message, $status);
        api_navigation::redirect($url);
    }
}

?>