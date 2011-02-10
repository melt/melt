<?php namespace nmvc\userx;

class UserxModule extends \nmvc\CoreModule {

    public static function beforeRequestProcess() {
        if (REQ_IS_CORE_DEV_ACTION)
            return;
        // Check if the user has a remembered login key.
        if (get_user() === null && isset($_COOKIE['REMBR_USR_KEY'])) {
            $time = time();
            $auth_user = UserModel::select()
            ->byKey(array("user_remember_key" => $_COOKIE['REMBR_USR_KEY']))
            ->and("user_remember_key_expires")->isMoreThan($time)->first();
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


