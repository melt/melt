<?php

namespace nanomvc\userx;

/**
 * @desc Hashes passwords to their storable form.
 *       Compatible with UNIX password files (crypt).
 * @return string One-way encrypted (Hashed) password.
 */
function hashPassword($cleartext_password) {
    return crypt($cleartext_password, "$1$" . api_string::random_hex_str(8) . "$");
}

/**
 * @desc Returns true if the cleartext password matches the hashed password.
 * @return boolean If password matches.
 */
function validatePassword($hashed_password, $cleartext_password) {
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
function logout() {
    if (!isset($_SESSION['auth']['shells']) || count($_SESSION['auth']['shells']) == 0) {
        unset($_SESSION);
        messenger\redirectMessage(url(config\LOGOUT_URL), __("You are now logged out."), "good");
    } else {
        $_SESSION['auth']['user'] = array_pop($_SESSION['auth']['shells']);
        messenger\redirectMessage(url(config\LOGOUT_URL), __("You logged out but are still logged in with your previous user."), "good");
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
            messenger\redirectMessage(url($redirect_local_url), __("Login failed! Your login shell depth is too high (max 64)!"));
        array_push($_SESSION['auth']['shells'], $_SESSION['auth']['user']);
    }
    $_SESSION['auth']['user'] = $user->getID();
    messenger\redirectMessage(url($redirect_local_url), __("Welcome %s!", escape($user->name)), "good");
}

function getUser() {
    static $authUser = null;
    if ($authUser !== null)
        return $authUser;
    // Automatic login handler.
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Handle login attempt.
        $username = strfy($_POST['username'], 128);
        $cleartext_password = $_POST['password'];
        $user = UserModel::selectFirst("username = $username");
        if ($user !== false) {
            $hashed_password = $user->password->get();
            if (validatePassword($hashed_password, $cleartext_password)) {
                $user->last_login_time->set(time());
                $user->last_login_ip->setToRemoteAddr();
                $user->store();
                // Replace any current shell with this shell.
                logout();
                login($user, config\LOGIN_URL);
            }
        }
        define("LAST_USERNAME_ATTEMPT", substr($_POST['username'], 0, 64));
        messenger\showMessage(__("Invalid username or password!"));
    }
    // Authorization: Check if user is (still) logged in.
    if (isset($_SESSION['auth'])) {
        $timeout = intval($_SESSION['auth']['timeout']);
        if (time() > $timeout) {
            // It doesn't matter how many shells are stacked,
            // if the top user times out - all users time out.
            unset($_SESSION['auth']);
            // Forward user to timeout page.
            messenger\redirectMessage(url(config\SESSION_TIMEOUT_URL), __("Your session expired due to inactivity. You need to log in again."));
        } else {
            $_SESSION['auth']['timeout'] = time() + config\SESSION_TIMEOUT_MINUTES * 60;
            return $authUser = UserModel::selectByID(intval($_SESSION['auth']['user']));
        }
    }
    return $authUser = false;
}

/**
 * Returns the username of the current login attempt.
 */
function getUsernameLoginAttempt() {
    return defined("LAST_USERNAME_ATTEMPT")? LAST_USERNAME_ATTEMPT: false;
}

