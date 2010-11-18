<?php
/**
 * Contains the configuration for this application.
 *
 * nanoMVC configuration options are named constants that resides in the
 * namespace \nanomvc\$MODULE\config where $MODULE is the module that uses the
 * configuration option.
 */

namespace nmvc {
    const APP_CONFIG = "1.3.1";
}

namespace nmvc\internal {
    /** Pass the names of non core modules to include to this function. */
    modules_using(
        "jquery"
    );
}


namespace nmvc\core\config {
    const TRANSLATION_ENABLED = false;
    // The following configuration options are explained here:
    // http://docs.nanomvc.com/chapter/development_guide/development_and_debugging
    const MAINTENANCE_MODE = true;
    const DEVELOPER_KEY = '';
    const DOWN_MESSAGE = '';
    const DISPLAY_DEVMODE_NOTICE = false;
    /** Forcing display of errors.
     * Overrides MAINTENANCE_MODE. *SECURITY RISK* */
    const FORCE_ERROR_DISPLAY = false;
    /** Overrides internal error flags and any other configuration
     * options that specifies error visibility. */
    const FORCE_ERROR_FLAGS = false;
    const RECAPTCHA_PUBLIC_KEY = '';
    const RECAPTCHA_PRIVATE_KEY = '';
    const PEAR_AUTOLOAD = true;
}


namespace nmvc\db\config {
    /** VERY RECOMMENDED. Increeses performance of queries and prevents
     * object id corruption by other software that accesses the database. */
    const USE_TRIGGER_SEQUENCING = true;
    /** Theese are standard database configuration options. */
    const HOST = '127.0.0.1';
    const NAME = 'test';
    const PREFIX = '';
    const USER = 'root';
    const PASSWORD = '';
    /** Set this to true to dump all queries made to
     * db_debug_query_benchmark.log Results in VERY POOR PERFORMANCE. */
    const DEBUG_QUERY_BENCHMARK = false;
}

namespace nmvc\translate\config {
    /** Enables translation. */
    const ENABLE = false;
    /** Sets the table to use for translation. */
    const TRANSLATION_TABLE = "translation";
    /**
     * Set to true to enable translation capture, before translating.
     * This gives significant performance overhead so only enable
     * it before translation phase.
     */
    const TRANSLATION_CAPTURE = false;
}

namespace nmvc\mail\config {
    const SMTP_AUTH_PASSWORD = 'password';
    const SMTP_AUTH_USER = 'user';
    const SMTP_AUTH_ENABLE = true;
    const SMTP_FROM_HOST = "testhost";
    const SMTP_TIMEOUT = 5;
    const SMTP_PORT = 26;
    /**
     * Optional. The desired name used by nanoMVC to
     * mail FROM when sending e-mail.
     */
    const FROM_NAME = "test";
    /**
     * The desired mail address used by nanoMVC to
     * mail FROM when sending e-mail.
     */
    const FROM_ADDRESS = "test@nanomvc.com";
    /**
     * The SMTP host used when sending mail.
     */
    const SMTP_HOST = "127.0.0.1";
}

// Module userx (only used if enabled)
namespace nmvc\userx\config {
    const LAST_DENY_AUTOREDIRECT = true;
    const COOKIE_HOST = NULL;
    const SHELL_LOGIN = false;
    const MULTIPLE_GROUPS = false;
    const MULTIPLE_IDENTITIES = false;
    const HASHING_ALGORITHM = 'crypt';
    const SOFT_403 = false;
    const REMEMBER_ME_DAYS = 356;
    /** Time before logged in sessions time out. */
    const SESSION_TIMEOUT_MINUTES = 60;

    /** Local URL where timeout sessions should be refered to. */
    const SESSION_TIMEOUT_URL = '/';

    /** Local URL where logged in users should be sent. */
    const LOGIN_URL = '/';

    /** Local URL where logged out users should be sent. */
    const LOGOUT_URL = '/';
}

// Module jquery (only used if enabled)
namespace nmvc\jquery\config {
    const INCLUDE_JQUERY_JSTREE = false;
    const INCLUDE_JQUERY_HOTKEYS = false;
    const INCLUDE_JQUERY_RESIZE = false;
    const INCLUDE_JQUERY_FORM = false;
    const INCLUDE_JQUERY_COOKIE = false;
    const INCLUDE_JQUERY_AUTORESIZE = false;
    /** The theme used by jquery-ui. Set this to null to not include it. */
    const JQUERY_UI_THEME = "smoothness";

    /**
     * For performance reasons you might want to disable the
     * includes you don't use.
     */
    const INCLUDE_JQUERY_CORNER = false;
    const INCLUDE_JQUERY_LIGHTBOX = false;
    const INCLUDE_JQUERY_DATATABLES = false;
    const INCLUDE_JQUERY_AUTOCOMPLETE = false;
}
