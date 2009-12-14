<?php

class Config {
    // The site unique identifier.
    public static $site_id = 'noid';

    // The name of the site.
    public static $site_name = 'Nameless Site';

    // The root url is used when generating anchors and links.
    public static $rooturl = 'http://localhost/';

    // The root url is parsed into theese three variables.
    public static $root_protocol = 'http';
    public static $root_host = 'localhost';
    public static $root_path = '/';

    // The cryptographic salt used by nanoMVC. Change from default for additional security.
    public static $crypt_salt = "\x36\xf1\xaa\x02\x38\xfb\x7c\x47\x55\x01\xbf\x32\x71\x92\xf3\x19";

    // When true, will shut down the site telling visitors that it's under maintence.
    public static $maintence = false;
    // Optional. Ammount of time the site will be down to notify visitors.
    public static $downshedule = '';

    // The maximum error log size in kilobytes. When this limit is reached, errors will not be appended.
    public static $max_logsize = 1000;

    // Desired developer cookie key to access the site in development/maintence mode.
    public static $dev_key = false;

    // Optional. The desired name used by Vector to mail FROM when sending e-mail.
    public static $email_name = '';
    // The desired mail address used by Vector to mail FROM when sending e-mail.
    public static $email_address = 'Vector@localhost';
    // The SMTP host Vector will use when sending mail.
    public static $email_smtp = 'localhost';
    // An e-mail address that reaches an administrator for administrative notifications and other site related messages.
    public static $email_admin = 'admin@localhost';

    // Database configuration.
    public static $sql_driver = 'mysql';
    public static $sql_host = 'localhost';
    public static $sql_user = 'root';
    public static $sql_password = '';
    public static $sql_database = 'site_database';
    public static $sql_prefix = '';

    public static function _evaluate() {
        // Parse the root url.
        $parts = @parse_url(Config::$rooturl);
        Config::$root_protocol = @$parts['scheme'];
        Config::$root_host = @$parts['host'];
        Config::$root_path = @$parts['path'];
        // Read the driver specified.
        $drivermap = array("mysql" => api_database::DRIVER_MYSQL,
                           "mssql" => api_database::DRIVER_MSSQL);
        if (isset($drivermap[CONFIG::$sql_driver]))
            Config::$sql_driver = $drivermap[Config::$sql_driver];
        else
            thpre_panic("Unknown database driver specified. Supported drivers: mysql,mssql");
        // Evaluate developer mode based on configuration and cookies.
        define('devmode', CONFIG::$maintence && (isset($_COOKIE['devkey']) && ($_COOKIE['devkey'] === CONFIG::$dev_key)));
        CONFIG::$dev_key = null;
        // Evaluate the table prefix.
        define('_tblprefix', ((CONFIG::$sql_prefix == '')? '': CONFIG::$sql_prefix . '_'));
    }
}


// Find the bootstrap and include the configuration there.
foreach (get_included_files() as $path) {
    if (substr($path, -13) == "bootstrap.php") {
        define("APP_DIR", dirname($path));
        require APP_DIR . "/config.php";
        Config::_evaluate();
        return;
    }
}
pre_panic("config.php not found in bootstrap path!");


?>
