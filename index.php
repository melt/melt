<?php // nanoMVC bootstrap install script.
error_reporting(E_ALL);
function write($what) { echo "$what<br />"; }
if (!isset($_POST["gobutton"])) {
    ?><h1>nanoMVC Bootstrap/Install Script</h1><?php
    write("No, this install script does not have a cool style. But do you <i>really</i> care?");
    write("Checking PHP version...");
    if (version_compare(PHP_VERSION, '5.3.0', '<')) {
        write("nanoMVC requires PHP 5.3.0 or newer! You have PHP " . PHP_VERSION);
        exit;
    }
    write("Checking PHP magic quotes...");
    if (get_magic_quotes_gpc())
        write("WARNING: You have magic quotes enabled! nanoMVC will attempt to counter this setting but you should turn it OFF since magic quotes is depricated, corrupts input data and reduces performance.");
    ?>
    <h3>Enter a database configuration for your config.php file:</h3>
    <form action="" method="post">
        Database host:<br />
        <input type="text" name="dbhost" value="localhost" /><br /><br />
        Database name:<br />
        <input type="text" name="dbname" value="nanomvc" /><br /><br />
        Database username:<br />
        <input type="text" name="dbusrname" value="root" /><br /><br />
        Database password:<br />
        <input type="text" name="dbpwd" value="" /><br /><br />
        Database prefix:<br />
        <input type="text" name="dbprefix" value="nmvc_" /><br /><br />
        <input type="submit" name="gobutton" value="Go!" />
    </form>
    <? exit;
} else {
    write("Writing config file...");
    if (!file_exists("config.php")) {
        $host = $_POST["dbhost"];
        $name = $_POST["dbname"];
        $user = $_POST["dbusrname"];
        $pwd = $_POST["dbpwd"];
        $prefix = $_POST["dbprefix"];
        $config_file = file_get_contents("config.php.default");
        function ins($before, $insert) {
            global $config_file;
            $pos = strpos($config_file, $before) + strlen($before);
            $pos2 = strpos($config_file, '"', $pos);
            $config_file = substr($config_file, 0, $pos) . $insert . substr($config_file, $pos2);
        }
        ins('const HOST = "', $host);
        ins('const USER = "', $user);
        ins('const PASSWORD = "', $pwd);
        ins('const NAME = "', $name);
        ins('const PREFIX = "', $prefix);
        ins('const ROOT_URL = "', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"]);
        file_put_contents("config.php", $config_file);
    } else
        write("File config.php already exists. Skipped!");
    write("Writing htaccess file...");
    if (!file_exists(".htaccess")) {
        $config_file = file_get_contents(".htaccess.default");
        $config_file = str_replace(" /bootstrap.php", " " . $_SERVER["REQUEST_URI"] . "bootstrap.php", $config_file);
        $config_file = str_replace("RewriteBase /", "RewriteBase " . $_SERVER["REQUEST_URI"], $config_file);
        file_put_contents(".htaccess", $config_file);
    } else
        write("File .htaccess already exists. Skipped!");
    write("Removing installation scripit (index.php)...");
    unlink("index.php");
    write("Were you expecting more steps? Sorry to disappoint.");
    write("Done, please reload!");
}
