<?php namespace nmvc\userx;

class UserxModule extends \nmvc\CoreModule {

    public static function beforeRequestProcess() {
        if (config\COOKIE_HOST !== null) {
            // Make sure that the session cookie is fixated to the configured
            // cookie host when set for the first time.
            // Since the session has already been started, the only way to
            // do this is to override the PHPSESSID header.
            if (!isset($_COOKIE['PHPSESSID']))
                \header("Set-Cookie: PHPSESSID=" . \session_id() . "; path=/; domain=" . config\COOKIE_HOST, true);
        }
    }

}


