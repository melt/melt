<?php
/* nanoMVC core configuration */

// The site unique identifier.
Config::$site_id = 'noid';

// The name of the site/application.
Config::$site_name = 'Some nanoMVC Site';

// The root url is used when generating anchors and links.
Config::$rooturl = 'http://localhost/';


/**** MAIL CONFIGURATION ****/

// Optional. The desired name used by Vector to mail FROM when sending e-mail.
Config::$email_name = '';
// The desired mail address used by Vector to mail FROM when sending e-mail.
Config::$email_address = 'Vector@localhost';
// The SMTP host Vector will use when sending mail.
Config::$email_smtp = 'localhost';
// An e-mail address that reaches an administrator for administrative notifications and other site related messages.
Config::$email_admin = 'admin@localhost';


/**** DATABASE CONFIGURATION ****/

Config::$sql_driver = 'mysql';
Config::$sql_host = 'localhost';
Config::$sql_user = 'root';
Config::$sql_password = '';
Config::$sql_database = 'nmvc';
Config::$sql_prefix = 'nmvc';

/**** DEVELOPER CONFIGURATION ****/

// When true, will shut down the site telling visitors that it's under maintence.
Config::$maintence = true;
// Optional. Will replace "Please try again in a moment." with any better EST message in the 503 Service Unavailable notification.
Config::$downshedule = '';
// Desired developer cookie key to access the site in development/maintence mode.
Config::$dev_key = 'dev_key_123';

// The maximum error log size in kilobytes. When this limit is reached, errors will not be appended.
Config::$max_logsize = 1000;

?>
