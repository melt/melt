<?php namespace melt\core; ?>
<p>
    You haven't configured a database yet. Melt Framework requires a database to
    function correctly.
</p>
<h2>Suggested Fix:</h2>
<p>
    It's required to use a database when developing applications
    for Melt Framework. Go to 
    <a href="<?php echo url("/core/console"); ?>">/core/console</a>
    and run "install" for more information.
</p>
<p>
    You can also enter the information manually
    in your config (<code>config.local.php</code>) like this:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
namespace melt\db\config {
    const HOST = 'localhost';
    const USER = 'root';
    const PASSWORD = '';
    const PORT = 3306;
    const NAME = 'mydb';
}
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>