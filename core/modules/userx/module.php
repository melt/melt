<?php namespace nmvc\userx;

class UserxModule extends \nmvc\CoreModule {

    public static function beforeRequestProcess() {
        // Make sure that the session cookie is fixated to the configured
        // cookie host when set for the first time.
        // Since the session has already been started, the only way to
        // do this is to override the PHPSESSID header.
        if (!isset($_COOKIE['PHPSESSID'])) {
            $cookie_host = config\COOKIE_HOST !== null? config\COOKIE_HOST: APP_ROOT_HOST;
            \header("Set-Cookie: PHPSESSID=" . \session_id() . "; path=/; domain=$cookie_host", true);
        }
        if (REQ_IS_CORE)
            return;
        // Check if the user has a remembered login key.
        if (get_user() === null && isset($_COOKIE['REMBR_USR_KEY'])) {
            $time = time();
            $auth_user = UserModel::select()->where("user_remember_key")->is($_COOKIE['REMBR_USR_KEY'])->and("user_remember_key_expires")->isMoreThan($time)->first();
            if ($auth_user === null) {
                unset_host_aware_cookie("REMBR_USR_KEY");
            } else {
                // Automatically log in.
                login($auth_user);
                \nmvc\messenger\redirect_message(REQ_URL, __("Remember login active. You where automatically logged in."), "good");
            }
        }
    }

}


