<div class="container" style="width: 800px; margin: 0 auto;text-align: center;">
<h1>A warm and cozy welcome to Melt!</h1>
<code>
<?php
/** Melt framework project bootstrapping script. */
function fail($errmsg) {
    die("<br /><b>Bootstrapping error:</b> $errmsg");
}
ob_implicit_flush(1);
print "Bootstrapping basic melt framework application structure so developer console can initialize.<br /><br />";
if (!isset($_GET["ignore_apache_warning"])) {
    if (!function_exists("apache_get_modules") || !function_exists("apache_get_version"))
        fail("Apache functions not found. The basic bootstrapper is currently written to bootstrap Melt Framework with Apache + mod_rewrite. If you are using a PHP+CGI setup this error is triggered because Melt cannot know what web server you are using. Please confirm that you are using Apache and has mod_rewrite installed, <a href=\"?ignore_apache_warning=1\">by clicking here to continue</a>.");
    if (!in_array("mod_rewrite", apache_get_modules()))
        fail("Melt Framework detected that you don't have the Apache module mod_rewrite installed/enabled. The basic bootstrapper is currently written to bootstrap Melt Framework with Apache + mod_rewrite. For instructions on how to install mod_rewrite, <a href=\"http://www.google.com/?q=install+mod_rewrite+apache+$operating_system\">go here</a>. To continue anyway, remove this file (" . __FILE__ . ") and copy all files in /core/scaffolding to the root directory yourself.");
    $webserver_version = apache_get_version();
} else {
    $webserver_version = "[Unknown Webserver]";
}
$operating_system = PHP_OS;
print "You are using: $webserver_version on $operating_system<br />";
if (version_compare(PHP_VERSION, "5.3") < 0)
    fail("Melt Framework requires at least PHP 5.3 since it uses advanced features such as namespaces and closures. Please upgrade to continue.<br />");
$app_is_64bit = PHP_INT_MAX > 0x7FFFFFFF;
if (!$app_is_64bit && !isset($_GET["ignore_32_warning"]))
    fail("You are not using 64 bit PHP! 32 bit PHP does not support 64 bit integers which has several problems. The most serious problem is that the application will eventually crash when the ID address space runs out. 64 bit PHP also has better performance and memory support. There is no reason to run 32 bit PHP on a 64 bit machine. If you undestand the risks and want to use 32 bit PHP anyway, <a href=\"?ignore_32_warning=1&ignore_apache_warning=1\">click here to continue</a>. <b>(Not recommended for production setups!)</b><br />");
chdir(__DIR__);
$rewrite_base = str_replace("\\", "/", dirname(@$_SERVER["PHP_SELF"]));
print "The application seem to be located in path '$rewrite_base'. Writing this to .htaccess RewriteBase directive.<br />";
$htaccess_content = <<<EOD
# Only utf-8 policy.
AddDefaultCharset utf-8

# Turn off indexing and directory slash correction.
Options -Indexes
DirectorySlash Off

# Enable symlinks.
Options +FollowSymLinks

# Start the rewrite engine.
RewriteEngine On

# Use melt to display error documents.
ErrorDocument 400 /core/core.php
ErrorDocument 401 /core/core.php
ErrorDocument 402 /core/core.php
ErrorDocument 403 /core/core.php
ErrorDocument 404 /core/core.php
ErrorDocument 405 /core/core.php
ErrorDocument 406 /core/core.php
ErrorDocument 407 /core/core.php
ErrorDocument 408 /core/core.php
ErrorDocument 409 /core/core.php
ErrorDocument 410 /core/core.php
ErrorDocument 411 /core/core.php
ErrorDocument 412 /core/core.php
ErrorDocument 413 /core/core.php
ErrorDocument 414 /core/core.php
ErrorDocument 415 /core/core.php
ErrorDocument 416 /core/core.php
ErrorDocument 417 /core/core.php
ErrorDocument 500 /core/core.php
ErrorDocument 501 /core/core.php
ErrorDocument 502 /core/core.php
ErrorDocument 503 /core/core.php
ErrorDocument 504 /core/core.php
ErrorDocument 505 /core/core.php

# Set this to the directory this application is installed in.
RewriteBase $rewrite_base

# Rewrite static module files to their appropriate locations.
RewriteRule ^static/cmod/([^/]+)/(.*)$ core/$1/static/$2 [L]
RewriteRule ^static/mod/([^/]+)/(.*)$ modules/$1/static/$2 [L]

# Rewrite everything non-static to melt core.
RewriteCond %{REQUEST_URI} !/static
RewriteRule .* core/core.php [L]
EOD;
print "Writing .htaccess file...<br />";
file_put_contents(".htaccess", $htaccess_content);
$dev_key = "";
for ($i = 0; $i < 16; $i++)
    $dev_key .= mt_rand() . "-";
$dev_key = substr(sha1($dev_key), 0, 12);
$dev_key_config = var_export($dev_key, true);
$additional_config = $app_is_64bit? "": "const IGNORE_64_BIT_WARNING = true;";
$config = <<<EOD
<?php
/**
 * This file is should contain your local, environment specific configuration.
 * Put all all of those here here (like MySQL configuration)
 * and make sure to ignore this file in your versioning system if you are
 * using one.
 * 
 * Any configuration directives here override those in config.php
 * because it is loaded before it.
 */

namespace melt\core\config {
    const MAINTENANCE_MODE = true;
    const DEVELOPER_KEY = $dev_key_config;
    $additional_config
}
EOD;
print "Writing initial configuration...<br />";
file_put_contents("config.local.php", $config);
print "Deleting bootstrap script.<br />";
unlink(__FILE__)
or fail("Could not delete \"" . __FILE__ . "\". Incorrect file system permissions?");
?>
</code>
<h2>Bootstrapped, broke and happy :-)</h2>
<p>That's right, we've successfully bootstrapped Melt and you're good to go!</b></p>
<p>
    Melt ships with a unix-like console interface that allows you control Melt and perform common tasks using any web browser. 
    The auto-generated <strong>developer key</strong> below is needed to access the console. If you lose it, check config.php.
</p>
<h2><code><?php echo $dev_key; ?></code></h2>

<p>
    The Melt documentation is available <a target="_blank" href="https://github.com/melt/melt/wiki">on GitHub</a>.<br/>
    Get started with Melt using the <a target="_blank" href="https://github.com/melt/melt/wiki/Getting-Started-with-Melt">Getting Started Guide</a>
    and become a pro Melter by reading the <a target="_blank" href="https://github.com/melt/melt/wiki/Solutions">Solutions</a> section.
    The Melt core team runs the company <a href="http://omnicloudapp.com" target="_blank">Omnicloud</a>, feel free to tweet us at <a href="http://twitter.com/omnicloud" target="_blank">@omnicloud</a> if you need any help.
</p>

<h2><a href="core/console">Nuff talking, launch the Melt Console and start building apps</a></h2>
    
</div>