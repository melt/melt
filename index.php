<link rel="stylesheet" href="http://meltframework.org/static/css/style.css" type="text/css" media="screen" />
<link rel="stylesheet" href="http://meltframework.org/static/css/buttons.css" type="text/css" media="screen" /> 
<script type='text/javascript' src='http://meltframework.org/static/js/cufon-yui.js?ver=3.1.4'></script>
<script type='text/javascript' src='http://meltframework.org/static/js/Gotham_300-Gotham_400-Gotham_italic_300-Gotham_italic_400.font.js?ver=3.1.4'></script>
<script type="text/javascript">                   
    Cufon.replace('h1, h2.subtitle, h2, h3.not-found',{fontFamily: 'Gotham', hover: true });
</script>
<div class="container" style="width: 800px; margin: 0 auto;">
<img src="http://meltframework.org/static/img/melt-logo.png" class="logo" alt="Melt Framework" />
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
<h2 class="subtitle">Bootstrapping <span>completed</span></h2>
<p>Successfully bootstrapped Melt Framework!</b></p>
<h2 class="subtitle">Developer Key <span></span></h2>
<h3><?php echo $dev_key; ?></h3>
<p>The developer key is auto-generated and required to access the console.<br/>If you lose it you need to enter a new key in config.php.</p>
<ul class="actions"><li><a class="green button" href="core/console">Launch Melt Console<span>The browser-based, unix-like console interface</span></a></li></ul>
</div>