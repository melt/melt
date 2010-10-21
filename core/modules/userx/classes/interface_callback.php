<?php namespace nmvc\userx;

class InterfaceCallback extends \nmvc\qmi\InterfaceCallback {

    public function ic_login() {
        $this->validate_failed_message = __("Invalid username or password!");
        $instances = $this->getInstances();
        $user = $instances['nmvc\userx\UserModel'][0];
        if ($this->doValidate() > 0)
            $this->doInvalidRedirect();
        if (login_challenge($user->username, $user->password, $user->remember_login)) {
            \nmvc\messenger\redirect_message($this->getSuccessUrl(), __("You are now logged in."), "good");
        } else {
            $this->pushError($user, "password", __("Invalid username or password!"));
            $this->doInvalidRedirect();
        }
    }

}
