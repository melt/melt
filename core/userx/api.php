<?php namespace melt\userx;

/**
 * Hashes passwords to their storable form.
 * How this is done exactly depends on the hashing algorithm configured.
 * @param string $cleartext_password
 * @return string One-way encrypted (Hashed) password.
 */
function hash_password($cleartext_password) {
    if (config\HASHING_ALGORITHM == "crypt") {
        return crypt($cleartext_password, "$1$" . \melt\string\random_hex_str(8) . "$");
    } else if (config\HASHING_ALGORITHM == "sha1") {
        $salt = \melt\string\random_hex_str(40);
        return $salt . sha1($salt . $cleartext_password, false);
    } else if (config\HASHING_ALGORITHM == "md5") {
        $salt = \melt\string\random_hex_str(32);
        return $salt . \md5($salt . $cleartext_password, false);
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
function deny($message = null) {
    // Ignore soft 403 for ajax requests as redirections is transparent.
    if (config\SOFT_403 != false && !\melt\request\is_ajax()) {
        $user = get_user();
        if ($user === null) {
            if (config\LAST_DENY_AUTOREDIRECT)
                $_SESSION['userx\LAST_DENY_PATH'] = APP_ROOT_URL . \substr(REQ_URL, 1);
            if ($message === null)
                $message = _("Access denied. You are not logged in.");
            \melt\messenger\redirect_message(config\SOFT_403, $message, "bad");
        } else {
            if ($message === null)
                $message = _("Access denied. Insufficient permissions.");
            \melt\messenger\redirect_message(config\SOFT_403, $message, "bad");
        }
    } else
        \melt\request\show_xyz(403);
    exit;
}

/**
 * Sets cookie with respect to current host
 * and userx cookie host configuration.
 * @param string $name
 * @param string $value
 * @param integer $expires
 * @return void
 */
function set_cookie($name, $value, $expires = 0) {
    setcookie($name, $value, $expires, "/");
}

/**
 * Sets cookie with respect to current host
 * and userx cookie host configuration.
 * @param string $name
 * @return void
 */
function unset_cookie($name) {
    setcookie($name, "", 0, "/");
}

/**
 * Attempts to login with the specified username and cleartext password.
 * Returns true if login was successful.
 * @param string $username
 * @param string $cleartext_password
 * @param boolean $remember_session
 * @return boolean
 */
function login_challenge($username, $cleartext_password, $remember_session = false) {
    if (config\MULTIPLE_IDENTITIES) {
        $user_identity = UserIdentityModel::select()->byKey(array("username" => $username))->first();
        $user = ($user_identity !== null)? $user_identity->user: null;
    } else
        $user = UserModel::select()->byKey(array("username" => $username))->first();
    if ($user !== null) {
        $hashed_password = $user->type("password")->getStoredHashedValue();
        if (validate_password($hashed_password, $cleartext_password)) {
            if (!$user->loginChallengeFilter())
                return false;
            $user->last_login_time = time();
            $user->type("last_login_ip")->setToRemoteAddr();
            if ($remember_session) {
                $user_remember_key = \melt\string\random_hex_str(16);
                $user->user_remember_key = $user_remember_key;
                $user->user_remember_key_expires = $expires = time() + 60 * 60 * 24 * intval(config\REMEMBER_ME_DAYS);
                // Remember user, allowing auto-login for time period configured.
                set_cookie("REMBR_USR_KEY", $user_remember_key, $expires);
            } else if (isset($_COOKIE["REMBR_USR_KEY"]))
                unset_cookie("REMBR_USR_KEY");
            $user->store();
            // Remember username for two years.
            set_cookie("LAST_USER", $username, time() + 60 * 60 * 24 * 365 * 2);
            // Stack any current shell on top of this shell.
            login($user);
            return true;
        }
    }
    return false;
}

/**
 * Ends the current logged in shell.
 */
function logout() {
    if (!isset($_SESSION['userx\auth']['shells']) || count($_SESSION['userx\auth']['shells']) == 0) {
        unset($_SESSION['userx\auth']);
        // Make sure remember keys are forgotten when logging out.
        if (isset($_COOKIE["REMBR_USR_KEY"])) {
            // Unset this key, whomever it belongs too.
            if (strlen($_COOKIE["REMBR_USR_KEY"]) == 16) {
                $user = UserModel::select()->byKey(array("user_remember_key" => $_COOKIE['REMBR_USR_KEY']))->first();
                if ($user !== null) {
                    $user->user_remember_key = "";
                    $user->store();
                }
            }
            unset_cookie("REMBR_USR_KEY");
        }
    } else {
        $_SESSION['userx\auth']['user'] = array_pop($_SESSION['userx\auth']['shells']);
    }
}

/**
 * Returns the shell stack count.
 * Will only return 1 or 0 if SHELL_LOGIN is disabled. Otherwise the shell
 * stack can be higher.
 * @return integer
 */
function get_shell_count() {
    get_user();
    if (!isset($_SESSION['userx\auth']))
        return 0;
    if (!config\SHELL_LOGIN)
        return 1;
    else
        return isset($_SESSION['userx\auth']['shells'])? \count($_SESSION['userx\auth']['shells']) + 1: 1;
}

/**
 * Will login as the specified user. Login will take place in a new
 * shell if SHELL_LOGIN is enabled, otherwise the new session will replace
 * the current.
 * @param UserModel $user User to login as.
 */
function login(UserModel $user) {
    if (!config\SHELL_LOGIN)
        unset($_SESSION['userx\auth']);
    $_SESSION['userx\auth']['timeout'] = config\SESSION_TIMEOUT_MINUTES !== false? time() + config\SESSION_TIMEOUT_MINUTES * 60: \PHP_INT_MAX;
    if (isset($_SESSION['userx\auth']['user'])) {
        if (!isset($_SESSION['userx\auth']['shells']))
            $_SESSION['userx\auth']['shells'] = array();
        else if (count($_SESSION['userx\auth']['shells']) > 64)
            \melt\messenger\redirect_message(REQ_URL, __("Login failed! Your login shell depth is too high (max 64)!"));
        array_push($_SESSION['userx\auth']['shells'], $_SESSION['userx\auth']['user']);
    }
    $_SESSION['userx\auth']['user'] = $user->getID();
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
    if (isset($_SESSION['userx\auth'])) {
        // Check if the user has timed out.
        if (time() > intval(@$_SESSION['userx\auth']['timeout'])) {
            // It doesn't matter how many shells are stacked,
            // if the top user times out - all users time out.
            unset($_SESSION['userx\auth']);
            // Forward user to timeout page.
            \melt\messenger\redirect_message(REQ_URL, __("Your session expired due to inactivity. You need to log in again."));
        }
        // Get the user and cache it.
        $_SESSION['userx\auth']['timeout'] = config\SESSION_TIMEOUT_MINUTES !== false? time() + config\SESSION_TIMEOUT_MINUTES * 60: \PHP_INT_MAX;
        $auth_user = UserModel::selectByID(intval($_SESSION['userx\auth']['user']));
        if ($auth_user === null) {
            // Account does not exist, just redirect.
            logout();
            \melt\request\redirect(REQ_URL);
        }
        // Call prototyped login session validation.
        $error = $auth_user->sessionValidate();
        if ($error != "") {
            logout();
            deny($error);
        }
        if (config\LAST_DENY_AUTOREDIRECT) {
            // Handle autoredirection.
            if ($auth_user !== null && isset($_SESSION['userx\LAST_DENY_PATH'])) {
                $path = $_SESSION['userx\LAST_DENY_PATH'];
                unset($_SESSION['userx\LAST_DENY_PATH']);
                \melt\request\redirect($path);
            }
        }
    } else
        $auth_user = null;
    return $auth_user;
}