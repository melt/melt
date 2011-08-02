<?php namespace melt\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    The file was expected to (but didn't) declare a class with the
    <em>exact</em> name
    (including casing): <code><?php echo $this->expected_name; ?></code>.
</p>
<h2>Suggested Fix:</h2>
<p>
    Change your current class declaration to something like:
</p>
<p>
    <?php define("foo", "bar"); ?>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo "<?php"; ?> namespace <?php echo preg_replace('#\\\\[^\\\\]*$#', "", $this->expected_name); ?>;

    class <?php echo preg_replace('#^([^\\\\]+\\\\)*#', "", $this->expected_name) ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>