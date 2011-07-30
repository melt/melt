<link rel="stylesheet" href="http://meltframework.org/style.css" type="text/css" media="screen" />
<link rel="stylesheet" href="http://meltframework.org/buttons.css" type="text/css" media="screen" /> 
<script type='text/javascript' src='http://meltframework.org/js/cufon-yui.js?ver=3.1.4'></script>
<script type='text/javascript' src='http://meltframework.org/js/Gotham_300-Gotham_400-Gotham_italic_300-Gotham_italic_400.font.js?ver=3.1.4'></script>
<script type="text/javascript">                   
    Cufon.replace('h1, h2.subtitle, h2, h3.not-found',{fontFamily: 'Gotham', hover: true });
</script>
<div class="container" style="text-align:center;">
<img src="http://meltframework.org/images/melt-logo.png" class="logo" alt="Melt Framework" />

<?php
/** Melt framework project bootstrapping script. */
function fail($errmsg) {
    die("<br /><b>Bootstrapping failed:</b> $errmsg");
}
ob_implicit_flush(1);
print "<pre style=\"white-space: pre-wrap;\">Bootstrapping basic melt framework application structure so developer console can initialize.<br /><br />";
if (!function_exists("apache_get_modules") || !function_exists("apache_get_version"))
    fail("Apache functions not found. You are not running Melt Framework in Apache! The basic bootstrapper is currently written to bootstrap Melt Framework with Apache + mod_rewrite. To continue anyway, remove this file (" . __FILE__ . ") and copy all files in /core/scaffolding to the root directory yourself.");
$operating_system = PHP_OS;
if (stristr($operating_system, "win") !== false)
    $operating_system = "Windows";
print "You are using: " . apache_get_version() . " on $operating_system<br />";
if (!in_array("mod_rewrite", apache_get_modules()))
    fail("Melt Framework detected that you don't have the Apache module mod_rewrite installed/enabled. The basic bootstrapper is currently written to bootstrap Melt Framework with Apache + mod_rewrite. For instructions on how to install mod_rewrite, <a href=\"http://www.google.com/?q=install+mod_rewrite+apache+$operating_system\">go here</a>. To continue anyway, remove this file (" . __FILE__ . ") and copy all files in /core/scaffolding to the root directory yourself.");
if (version_compare(PHP_VERSION, "5.3") < 0)
    fail("Melt Framework requires at least PHP 5.3 since it uses advanced features such as namespaces and closures. Please upgrade to continue.<br />");
$app_is_64bit = PHP_INT_MAX > 0x7FFFFFFF;
if (!$app_is_64bit && !isset($_GET["ignore_32_warning"]))
    fail("You are not using 64 bit PHP! 32 bit PHP does not support 64 bit integers which has several problems. The most serious problem is that the application will eventually crash when the ID address space runs out. 64 bit PHP also has better performance and memory support. There is no reason to run 32 bit PHP on a 64 bit machine. If you undestand the risks and want to use 32 bit PHP anyway, <a href=\"?ignore_32_warning=1\">click here to continue</a>. <b>(Not recommended for production setups!)</b><br />");
chdir(__DIR__);
if (!is_dir("core/scaffolding"))
    fail("Could not find folder 'core/scaffolding'!");
foreach (glob("core/scaffolding/*", GLOB_BRACE) as $file) {
    if (file_exists(basename($file))) {
        print "'$file' already exists, ignoring...<br />";
        continue;
    }
    if (is_dir($file)) {
        print "Creating directory \"$file\".<br />";
        mkdir(basename($file))
        or fail("Could not create directory \"" . basename($file) . "\". Incorrect file system permissions?");
    } else {
        print "Creating file \"$file\".<br />";
        copy($file, basename($file))
        or fail("Could not create file \"" . basename($file) . "\". Incorrect file system permissions?");
    }
}
$htaccess_content = file_get_contents("core/scaffolding/.htaccess");
$rewrite_base = str_replace("\\", "/", dirname(@$_SERVER["PHP_SELF"]));
print "The application seem to be located in path '$rewrite_base'. Writing this to .htaccess RewriteBase directive.<br />";
$htaccess_content = str_replace("RewriteBase /", "RewriteBase $rewrite_base", $htaccess_content);
print "Writing .htaccess file...<br />";
file_put_contents(".htaccess", $htaccess_content);
$dev_key = "";
for ($i = 0; $i < 16; $i++)
    $dev_key .= mt_rand() . "-";
$dev_key = substr(sha1($dev_key), 0, 12);
$config = file_get_contents("config.php");
$config .= "\n\n" . 'namespace melt\core\config {' . "\n    " . 'const DEVELOPER_KEY = ' . var_export($dev_key, true) . ';';
if (!$app_is_64bit)
    $config .= "\n    " . 'const IGNORE_64_BIT_WARNING = true;';
$config .= "\n}\n";
print "Writing initial configuration...<br />";
file_put_contents("config.php", $config);
print "Deleting bootstrap script.<br />";
//unlink(__FILE__)
//or fail("Could not delete \"" . __FILE__ . "\". Incorrect file system permissions?");
print "</pre>";
?>
<h2 class="subtitle">Bootstrapping <span>completed</span></h2>
<p>Successfully bootstrapped Melt Framework!</b></p>
<h2 class="subtitle">Developer Key <span></span></h2>
<h3><?php echo $dev_key; ?></h3>
<p>The developer key is auto-generated and required to access the console.<br/>If you lose it you need to enter a new key in config.php.</p>
<ul class="actions"><li><a class="green button" href="core/console">Launch Melt Console<span>The browser-based, unix-like console interface</span></a></li></ul>
</div>