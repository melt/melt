<?php namespace nmvc\userx;

/**
 * Hashes passwords to their storable form.
 * How this is done exactly depends on the hashing algorithm configured.
 * @param string $cleartext_password
 * @return string One-way encrypted (Hashed) password.
 */
function hash_password($cleartext_password) {
    if (config\HASHING_ALGORITHM == "crypt") {
        return crypt($cleartext_password, "$1$" . \nmvc\string\random_hex_str(8) . "$");
    } else if (config\HASHING_ALGORITHM == "sha1") {
        $salt = \nmvc\string\random_hex_str(40);
        return $salt . sha1($salt . $cleartext_password, false);
    } else if (config\HASHING_ALGORITHM == "md5") {
        $salt = \nmvc\string\random_hex_str(32);
        return $salt . md5($salt . $cleartext_password, false);
    } else
        trigger_error("The configured hashing algorithm '" . config\HASHING_ALGORITHM . "' is not supported.", \E_USER_ERROR);
}

/**
 * Returns true if the cleartext password matches the hashed password.
 * A match is defined as cleartext that, when salted and hashed the same way,
 * equals the given hash. The salt is embeded in the hash.
 * @param string $hashed_password
 * @param string $cleartext_password
 * @return boolean TRUE on password match.
 */
function validate_password($hashed_password, $cleartext_password) {
    if (!is_string($hashed_password) || !is_string($cleartext_password))
        return false;
    if (config\HASHING_ALGORITHM == "crypt") {
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
    } else if (config\HASHING_ALGORITHM == "sha1") {
        if (strlen($hashed_password) != 80)
            return false;
        $salt = substr($hashed_password, 0, 40);
        $hash = substr($hashed_password, 40);
        return $hash == sha1($salt . $cleartext_password, false);
    } else if (config\HASHING_ALGORITHM == "md5") {
        if (strlen($hashed_password) != 64)
            return false;
        $salt = substr($hashed_password, 0, 32);
        $hash = substr($hashed_password, 32);
        return $hash == sha1($salt . $cleartext_password, false);
    } else
        trigger_error("The configured hashing algorithm '" . config\HASHING_ALGORITHM . "' is not supported.", \E_USER_ERROR);
}

/**
 * Will deny user access. Action taken is determined by configuration.
 * Function does not return.
 */
function deny() {
    if (config\SOFT_403) {
        $user = get_user();
        if ($user === null)
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, __("Access denied. You are not logged in."), "bad");
        else
            \nmvc\messenger\redirect_message(config\LOGIN_URL, __("Access denied. Insufficient permissions."), "bad");
    } else
        \nmvc\request\show_xyz(403);
    exit;
}

/**
 * Will terminate this shell.
 */
function logout($redirect = true) {
    if (!isset($_SESSION['auth']['shells']) || count($_SESSION['auth']['shells']) == 0) {
        unset($_SESSION['auth']);
        // Make sure remember keys are forgotten when logging out.
        if (isset($_COOKIE["REMBR_USR_KEY"])) {
            // Unset this key, whomever it belongs too.
            if (strlen($_COOKIE["REMBR_USR_KEY"]) == 16) {
                $user = UserModel::selectFirst("user_remember_key = " . strfy($_COOKIE['REMBR_USR_KEY']));
                if ($user !== null) {
                    $user->user_remember_key = "";
                    $user->store();
                }
            }
            setcookie("REMBR_USR_KEY", "", 0, config\COOKIE_HOST !== null? config\COOKIE_HOST: APP_ROOT_PATH);
        }
        if ($redirect)
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, __("You are now logged out."), "good");
    } else {
        $_SESSION['auth']['user'] = array_pop($_SESSION['auth']['shells']);
        if ($redirect)
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, __("You logged out but are still logged in with your previous user."), "good");
    }
}

/**
 * Will login as the specified user. Login will take place in a new
 * shell if SHELL_LOGIN is enabled, otherwise the new session will replace
 * the current.
 * @param UserModel $user User to login as.
 * @param string $url Login or full url to redirect to afterwards.
 */
function login(UserModel $user, $url = "/", $login_message = null) {
    if (!config\SHELL_LOGIN)
        unset($_SESSION['auth']);
    $_SESSION['auth']['timeout'] = time() + config\SESSION_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['auth']['user'])) {
        if (!isset($_SESSION['auth']['shells']))
            $_SESSION['auth']['shells'] = array();
        else if (count($_SESSION['auth']['shells']) > 64)
            \nmvc\messenger\redirect_message($url, __("Login failed! Your login shell depth is too high (max 64)!"));
        array_push($_SESSION['auth']['shells'], $_SESSION['auth']['user']);
    }
    $_SESSION['auth']['user'] = $user->getID();
    if ($login_message === null)
        $login_message = __("You are now logged in.");
    \nmvc\messenger\redirect_message($url, $login_message, "good");
}

/**
 * Returns the current logged in user or NULL if not logged in.
 * May redirect.
 * @return UserModel
 */
function get_user() {
    static $auth_user = false;
    if ($auth_user !== false)
        return $auth_user;
    // Automatic login handler.
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Handle login attempt.
        $username = strfy($_POST['username'], 512);
        $cleartext_password = $_POST['password'];
        if (config\MULTIPLE_IDENTITIES) {
            $user_identity = UserIdentityModel::selectFirst("username = $username");
            $user = ($user_identity !== null)? $user_identity->user: null;
        } else
            $user = UserModel::selectFirst("username = $username");
        if ($user !== null) {
            $hashed_password = $user->password;
            if (validate_password($hashed_password, $cleartext_password)) {
                $user->last_login_time = time();
                $user->type("last_login_ip")->setToRemoteAddr();
                if (isset($_POST['remember_me'])) {
                    $user_remember_key = \nmvc\string\random_hex_str(16);
                    $user->user_remember_key = $user_remember_key;
                    $user->user_remember_key_expires = $expires = time() + 60 * 60 * 24 * intval(config\REMEMBER_ME_DAYS);
                    // Remember user, allowing auto-login for time period configured.
                    setcookie("REMBR_USR_KEY", $user_remember_key, $expires, config\COOKIE_HOST !== null? config\COOKIE_HOST: APP_ROOT_PATH);
                }
                $user->store();
                // Remember username for two years.
                setcookie("LAST_USER", $user->username, time() + 60 * 60 * 24 * 365 * 2, config\COOKIE_HOST !== null? config\COOKIE_HOST: APP_ROOT_PATH);
                // Replace any current shell with this shell.
                logout(false);
                login($user, config\LOGIN_URL);
            }
        }
        $_SESSION['userx\last_username_attempt'] = substr($_POST['username'], 0, 64);
        \nmvc\messenger\push_message(__("Invalid username or password!"));
        \nmvc\request\go_back();
    }
    if (isset($_SESSION['auth'])) {
        // Check if the user has timed out.
        if (time() > intval(@$_SESSION['auth']['timeout'])) {
            // It doesn't matter how many shells are stacked,
            // if the top user times out - all users time out.
            unset($_SESSION['auth']);
            // Forward user to timeout page.
            \nmvc\messenger\redirect_message(config\SESSION_TIMEOUT_URL, __("Your session expired due to inactivity. You need to log in again."));
        }
        // Get the user and cache it.
        $_SESSION['auth']['timeout'] = time() + config\SESSION_TIMEOUT_MINUTES * 60;
        $auth_user = UserModel::selectByID(intval($_SESSION['auth']['user']));
        if ($auth_user === null) {
            // Account does not exist, just redirect.
            logout(false);
            \nmvc\request\redirect(url(config\SESSION_TIMEOUT_URL));
        }
        // Call prototyped login session validation.
        $error = $auth_user->sessionValidate();
        if ($error != "") {
            logout(false);
            \nmvc\messenger\redirect_message(config\LOGOUT_URL, $error);
        }
    } else {
        // Check if the user has a remembered login key.
        if (!isset($_COOKIE['REMBR_USR_KEY']))
            return $auth_user = null;
        $time = time();
        $auth_user = UserModel::selectFirst("user_remember_key = " . strfy($_COOKIE['REMBR_USR_KEY']) . " AND user_remember_key_expires > $time");
        if ($auth_user === null) {
            setcookie("REMBR_USR_KEY", "", 0, config\COOKIE_HOST !== null? config\COOKIE_HOST: APP_ROOT_PATH);
            return null;
        } else {
            // Replace any current shell with this shell.
            logout(false);
            login($auth_user, config\LOGIN_URL, __("Remember login active. You where automatically logged in."));
        }
    }
    return $auth_user;
}

/**
 * Returns the username of the current login attempt.
 */
function get_username_login_attempt() {
    if (isset($_SESSION['userx\last_username_attempt'])) {
        static $registered = false;
        if (!$registered) {
            \register_shutdown_function(function() {
                unset($_SESSION['userx\last_username_attempt']);
            });
            $registered = true;
        }
        return $_SESSION['userx\last_username_attempt'];
    } else
        return @$_COOKIE["LAST_USER"];
}
