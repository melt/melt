<?php namespace nmvc\userx;

class UserxModule extends \nmvc\Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Omnicloud AB, ©$year";
    }

    public static function getInfo() {
        return "<b>userx - flexible and secure user rights management</b>"
        . "This module may not be used without a valid license.";
    }

    public static function getVersion() {
        return "1.5.0";
    }

    /**
     * Overridable event-function.
     * Called just before the request is processed and evaluated
     * for further routing.
     */
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