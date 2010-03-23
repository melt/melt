<?php

// Configuration for the authorization component.
if (!defined("SESSION_TIMEOUT_MINUTES"))
    define("SESSION_TIMEOUT_MINUTES", 30);
if (!defined("SESSION_TIMEOUT_URL"))
    define("SESSION_TIMEOUT_URL", url("/"));

class AuthorizationComponent {
    private static $authTimeout = 0;
    private static $authUser = false;
    private static $lastUsernameAttempt = null;

    /**
     * @desc Hashes passwords to their storable form.
     *       Compatible with UNIX password files (crypt).
     * @return string One-way encrypted (Hashed) password.
     */
    public static function hashPassword($cleartext_password) {
        return crypt($cleartext_password, "$1$" . api_string::random_hex_str(8) . "$");
    }

    /**
     * @desc Returns true if the cleartext password matches the hashed password.
     * @return boolean If password matches.
     */
    public static function validatePassword($hashed_password, $cleartext_password) {
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
     * @desc Call to get the current authorized user, or false if there are no souch user.
     * @returns Object Current user or otherwise false.
     */
    public static function getCurrentUser() {
        return self::$authUser;
    }

    public static function getLastUsernameAttempt() {
        return self::$lastUsernameAttempt;
    }

    /**
    * @desc Returns the timestamp when user will be logged out due to inactivity.
    */
    protected static function getLoginTimeout() {
        return intval(SESSION_TIMEOUT_MINUTES) * 60 + time();
    }

    /**
    * @desc For authorization to work, it needs to be run for every request.
    */
    public static function runAuthorization($usermodel, $username_field, $password_field, $last_login_field) {
        // Automatic login handler.
        if (isset($_POST['username']) && isset($_POST['password'])) {
            // Handle login attempt.
            $username = api_database::strfy($_POST['username'], 128);
            $cleartext_password = $_POST['password'];
            $user = forward_static_call(array($usermodel, "selectFirst"), "$username_field = $username");
            if ($user !== false) {
                $hashed_password = $user->$password_field->get();
                if (self::validatePassword($hashed_password, $cleartext_password)) {
                    $_SESSION['auth']['timeout'] = self::getLoginTimeout();
                    $_SESSION['auth']['user'] = $user->getID();
                    $user->$last_login_field->set(time());
                    $user->store();
                    if (isset($_SESSION['login_return'])) {
                        $goto = $_SESSION['login_return']['from'];
                        unset($_SESSION['login_return']);
                    } else
                        $goto = REQURL;
                    Flash::doFlashRedirect($goto, __("Welcome %s!", api_html::escape($user->username)), FLASH_GOOD);
                }

            }
            self::$lastUsernameAttempt = substr($_POST['username'], 0, 64);
            Flash::doFlash(__("Invalid username or password!"));
        }
        // Authorization: Check if user is (still) logged in.
        if (isset($_SESSION['auth'])) {
            $timeout = intval($_SESSION['auth']['timeout']);
            if (time() > $timeout) {
                unset($_SESSION['auth']);
                // Forward user to timeout page.
                Flash::doFlashRedirect(SESSION_TIMEOUT_URL, __("Your session expired due to inactivity. You need to log in again."));
            } else {
                self::$authTimeout = $_SESSION['auth']['timeout'] = self::getLoginTimeout();
                self::$authUser = forward_static_call(array($usermodel, "selectByID"), intval($_SESSION['auth']['user']));
            }
        }
        // Remove login return if browsing other pages.
        if (isset($_SESSION['login_return'])) {
            if ($_SESSION['login_return']['to'] === 0)
                $_SESSION['login_return']['to'] = _sysurl;
            else if (_sysurl != "/submit" && $_SESSION['login_return']['to'] !== _sysurl) {
                unset($_SESSION['login_return']);
            }
        }
    }

    /**
    * @desc Logs out the user, you should redirect/reload after calling.
    */
    public static function doLogout() {
        unset($_SESSION['auth']);
    }
}

?>
