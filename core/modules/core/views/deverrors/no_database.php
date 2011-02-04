<?php namespace nmvc\core; ?>
<p>
    You havn't configured a database yet. NanoMVC requires a database to
    function correctly.
</p>
<h2>Suggested Fix:</h2>
<p>
    It's recommended to use a local database when developing applications
    for NanoMVC. Install MySQL and create a database called 'nanomvc'.
    Then go to <code>config.local.php</code>
    and enter the authentication details like so:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
namespace nmvc\db\config {
    const HOST = 'localhost';
    const USER = 'root';
    const PASSWORD = '';
    const PORT = 3306;
    const NAME = 'nanomvc';
}
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>
<p>
    Afterwards NanoMVC must syncronize the database so it contains everything
    neccessary to be used. Do this by visiting 
    <a href="<?php echo url("/core/action/sync"); ?>">/core/action/sync</a>.
</p>