<?php
/**
* Preconditions:
*
* This components expects there to be a "user" model with the fields:
* username (text)
* password (text)
* lastlogin (int)
* lvl (int)
*/

// Configuration for the authorization component.
define("REGISTRATION_TIMEOUT_DAYS", 3);
define("SESSION_TIMEOUT_MINUTES", 30);

class AuthorizationComponent {
    const authBanned = -3;
    const authUnverifiedEmail = -2;
    const authUnactivated = -1;
    const authUnregistred = 0;
    const authUser = 1;
    const authSuperUser = 2;
    const authModerator = 3;
    const authAdministrator = 255;

    private static $authTimeout = 0;
    private static $authLevel = 0;
    private static $authUsername = null;
    private static $authUserID = 0;
    private static $authLastUsernameAttempt = null;

    /**
    * @desc Returns the timestamp when user should have activated their account.
    */
    protected static function getRegistrationDaysTimeout() {
        $days = intval(REGISTRATION_TIMEOUT_DAYS);
        if ($days < 1)
            $days = 1;
        return $days;
    }

    /**
    * @desc Returns the timestamp when user will be logged out due to inactivity.
    */
    protected static function getLoginTimeout() {
        return intval(SESSION_TIMEOUT_MINUTES) * 60 + time();
    }

    /**
    * @desc Call after running authorization to get the authorization variables.
    */
    public static function readAuthorization() {
        return array(&self::$authTimeout, &self::$authLevel, &self::$authUsername, &self::$authUserID, &self::$authLastUsernameAttempt);
    }

    /**
    * @desc For authorization to work, it needs to be run for every request.
    */
    public static function runAuthorization() {
        // Automatic login handler.
        if (isset($_POST['username']) && isset($_POST['password']))
            self::handleLogin();

        // Authorization: Check if user is (still) logged in.
        if (isset($_SESSION['auth'])) {
            $timeout = intval($_SESSION['auth']['timeout']);
            if (time() > $timeout) {
                unset($_SESSION['auth']);
            } else {
                self::$authTimeout = $_SESSION['auth']['timeout'] = self::getLoginTimeout();
                self::$authLevel = $_SESSION['auth']['level'];
                self::$authUsername = $_SESSION['auth']['usr'];
                self::$authUserID = $_SESSION['auth']['usrid'];
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
        // In 0.25% of all requests, it will remove non email verified accounts.
        if ((rand() % 400) == 0) {
            $days = self::getRegistrationDaysTimeout();
            $days = 0;
            $daystime = $days * 24 * 60 * 60;
            $time = time();
            User::removeWhere("(accesslvl = -2) AND (($time - registred) >= $daystime)");
        }
    }

    /**
    * @desc Logs out the user, you should redirect/reload after calling.
    */
    public static function doLogout() {
        unset($_SESSION['auth']);
    }

    private static function handleLogin() {
        // Handle login attempt.
        $usr = api_database::strfy($_POST['username'], 128);
        $pwd = api_database::strfy(sha1($_POST['password'] . CONFIG::$crypt_salt));

        $rows = User::selectWhere("username = $usr AND password = $pwd");
        if (count($rows) != 1) {
            self::$authLastUsernameAttempt = substr($_POST['username'], 0, 64);
            Flash::doFlash(__("Invalid username or password!"));
        } else {
            $usr = $rows[0];
            $lvl = intval($usr->lvl->get());
            if ($lvl == 0)
                throw new Exception("The zero user level is reserved for non authorized sessions only.");
            if ($lvl == -1) {
                Flash::doFlash(__("Your account has not been activated by an administrator yet. This can take a long time, be patient."));
                return;
            } else if ($lvl == -2) {
                $days = self::getRegistrationDaysTimeout();
                Flash::doFlash(__("Your e-mail address has not been verified yet. Follow the instructions in the email you should receive within 24 hours of registration. The account will be deleted around %i days after registration if the e-mail address are not verified.", $days));
                return;
            } else if ($lvl <= -3) {
                Flash::doFlash(__("Could not login, you are banned."));
                return;
            }
            $_SESSION['auth']['timeout'] = self::getLoginTimeout();
            $_SESSION['auth']['level'] = $lvl;
            $_SESSION['auth']['usr'] = strval($usr->username->get());
            $_SESSION['auth']['usrid'] = intval($usr->getID());
            $usr->lastlogin->set(time());
            $usr->store();
            if (isset($_SESSION['login_return'])) {
                $goto = $_SESSION['login_return']['from'];
                unset($_SESSION['login_return']);
            } else
                $goto = REQURL;
            Flash::doFlashRedirect($goto, __("Welcome, %s.", api_html::escape($usr->username)), FLASH_GOOD);
        }
    }
}

?>
