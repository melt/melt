<?php namespace melt\userx;

abstract class InterfaceCallback_app_overrideable extends \melt\qmi\InterfaceCallback {

    public function ic_login() {
        $this->validate_failed_message = __("Invalid username or password!");
        $this->doValidate();
        $instances = $this->getInstances();
        $user = $instances['melt\userx\UserModel'][0];
        if (login_challenge($user->username, $user->password, $user->remember_login)) {
            \melt\messenger\redirect_message($this->getSuccessUrl(), __("Successfully logged in."), "good");
        } else {
            $user->password = "";
            $this->pushError($user, "password", __("Invalid username or password!"));
            $this->doInvalidRedirect();
        }
    }

}
