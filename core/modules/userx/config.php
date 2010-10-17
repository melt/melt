<?php return array(
    /** Time before logged in sessions time out. */
    "SESSION_TIMEOUT_MINUTES" => false,
    /** Local URL where timeout sessions should be refered to. */
    "SESSION_TIMEOUT_URL" => false,
    /** Local URL where logged in users should be sent. */
    "LOGIN_URL" => '/',
    /** Local URL where logged out users should be sent. */
    "LOGOUT_URL" => '/',
    /** Days to remember a user that checks 'remember me'. */
    "REMEMBER_ME_DAYS" => 356,
    /** Use a soft 403 to redirect to login with warning instead of HTTP 403 crashing. */
    "SOFT_403" => false,
    /** Choose between a number of hashing algorithm that userx supports. */
    "HASHING_ALGORITHM" => "crypt",
    /** Set this to true to ignore the username column and instead use the
     * user identity model to enable multiple usernames per user instance. */
    "MULTIPLE_IDENTITIES" => false,
    /** Set this to true to ignore the group column and instead use the user
     * group model to enable multiple group memberships per user instance. */
    "MULTIPLE_GROUPS" => false,
    /** Set this to true to enable login in a shell structured manner.
     * This way a new login will temporarly override the current. */
    "SHELL_LOGIN" => false,
    /** Normally userx uses the current host for the session cookie and other
     * cookies it uses. Setting this to a string instead of null will however
     * use that value as host instead.  */
    "COOKIE_HOST" => null,
);