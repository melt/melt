<?php namespace melt\db;

class DbModule extends \melt\CoreModule {

    public static function beforeRequestProcess() {
        parent::beforeRequestProcess();
        // Throw away magic quotes, the standard database injection protection for badly written PHP code.
        if (\ini_get("magic_quotes_runtime") && \set_magic_quotes_runtime(0) === FALSE)
            \trigger_error("Unable to disable magic_quotes_runtime ini option!", \E_USER_ERROR);
        // Using a stripslashes callback for any gpc data.
        if (\get_magic_quotes_gpc()) {
            $stripslashes_deep_fn = function($value) use (&$stripslashes_deep_fn) {
                return is_array($value)? $stripslashes_deep_fn($value): stripslashes($value);
            };
            $_POST = \array_map($stripslashes_deep_fn, $_POST);
            $_GET = \array_map($stripslashes_deep_fn, $_GET);
            $_COOKIE = \array_map($stripslashes_deep_fn, $_COOKIE);
            $_REQUEST = \array_map($stripslashes_deep_fn, $_REQUEST);
        }
    }
    
}