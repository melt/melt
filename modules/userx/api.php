<?php namespace nmvc\userx;

/**
 * @desc Hashes passwords to their storable form.
 *       Compatible with UNIX password files (crypt).
 * @return string One-way encrypted (Hashed) password.
 */
function hash_password($cleartext_password) {
    return crypt($cleartext_password, "$1$" . \nmvc\string\random_hex_str(8) . "$");
}

/**
 * @desc Returns true if the cleartext password matches the hashed password.
 * @return boolean If password matches.
 */
function validate_password($hashed_password, $cleartext_password) {
    $salt_end = strrpos($hashed_password, "$");
    if ($salt_end === false) {
        // DES, first two charachers is salt.
        $salt = substr($hashed_password, 0, 2);
    } else {
        // Salt before $.
        $salt_end++;
        $salt = substr($hashed_password, 0, $salt_end);
    }
    return crypt($cleartext_password, $salt) == $hashed_password;
}

/**
 * Will terminate this shell.
 */
function logout($redirect = true) {
    if (!isset($_SESSION['auth']['shells']) || count($_SESSION['auth']['shells']) == 0) {
        unset($_SESSION['auth']);
        if ($redirect)
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, __("You are now logged out."), "good");
    } else {
        $_SESSION['auth']['user'] = array_pop($_SESSION['auth']['shells']);
        if ($redirect)
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, __("You logged out but are still logged in with your previous user."), "good");
    }
}

/**
 * Will login as the specified user in a new shell.
 * @param UserModel $user User to login as.
 * @param string $redirect_local_url Where to redirect afterwards.
 */
function login(UserModel $user, $redirect_local_url = "/") {
    $_SESSION['auth']['timeout'] = time() + config\SESSION_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['auth']['user'])) {
        if (!isset($_SESSION['auth']['shells']))
            $_SESSION['auth']['shells'] = array();
        else if (count($_SESSION['auth']['shells']) > 64)
            \nmvc\messenger\redirect_message($redirect_local_url, __("Login failed! Your login shell depth is too high (max 64)!"));
        array_push($_SESSION['auth']['shells'], $_SESSION['auth']['user']);
    }
    $_SESSION['auth']['user'] = $user->getID();
    \nmvc\messenger\redirect_message($redirect_local_url, __("You are now logged in."), "good");
}

/**
 * Returns the current logged in user or NULL if not logged in.
 * May redirect.
 * @return UserModel
 */
function get_user() {
    static $authUser = false;
    if ($authUser !== false)
        return $authUser;
    // Automatic login handler.
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Handle login attempt.
        $username = strfy($_POST['username'], 128);
        $cleartext_password = $_POST['password'];
        $user = UserModel::selectFirst("username = $username");
        if ($user !== null) {
            $hashed_password = $user->password;
            if (validate_password($hashed_password, $cleartext_password)) {
                $user->last_login_time = time();
                $user->type("last_login_ip")->setToRemoteAddr();
                if (isset($_POST['remember'])) {
                    $user_remember_key = \nmvc\string\random_hex_str(16);
                    $user->user_remember_key = $user_remember_key;
                    // Remember user, allowing auto-login for one year.
                    setcookie("REMBR_USR_KEY", $user_remember_key, time() + 60 * 60 * 24 * 365, url("/"));
                }
                $user->store();
                // Replace any current shell with this shell.
                logout(false);
                login($user, config\LOGIN_URL);
                // Remember username for two years.
                setcookie("LAST_USER", $username, time() + 60 * 60 * 24 * 365 * 2, url("/"));
            }
        }
        define("LAST_USERNAME_ATTEMPT", substr($_POST['username'], 0, 64));
        \nmvc\messenger\show_message(__("Invalid username or password!"));
    }
    if (!isset($_SESSION['auth'])) {
        // Check if the user has a remembered login key.
        if (!isset($_COOKIE['REMBR_USR_KEY']))
            return $authUser = null;
        $user = UserModel::selectFirst("user_remember_key = " . strfy($_COOKIE['REMBR_USR_KEY']));
        if ($user === null) {
            setcookie("REMBR_USR_KEY", "", 0);
            return $authUser = null;
        } else {
            // Replace any current shell with this shell.
            logout(false);
            login($user, config\LOGIN_URL);
        }
    // Check if the user has timed out.
    } else if (time() > intval($_SESSION['auth']['timeout'])) {
        // It doesn't matter how many shells are stacked,
        // if the top user times out - all users time out.
        unset($_SESSION['auth']);
        // Forward user to timeout page.
        \nmvc\messenger\redirect_message(config\SESSION_TIMEOUT_URL, __("Your session expired due to inactivity. You need to log in again."));
    }
    // Get the user and cache it.
    $_SESSION['auth']['timeout'] = time() + config\SESSION_TIMEOUT_MINUTES * 60;
    $authUser = UserModel::selectByID(intval($_SESSION['auth']['user']));
    // Remove login if user was deleted.
    if ($authUser === null)
        unset($_SESSION['auth']);
    return $authUser;
}

/**
 * Returns the username of the current login attempt.
 */
function get_username_login_attempt() {
    return defined("LAST_USERNAME_ATTEMPT")? LAST_USERNAME_ATTEMPT: false;
}