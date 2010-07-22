<?php namespace nmvc\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    The file declared the class <code><?php echo $this->class_name; ?></code>
    and is expected to (but didn't) directly extend the class
    <code><?php echo $this->must_extend; ?></code>
</p>
<h2>Suggested Fix:</h2>
<p>
    Change your current class declaration to something like:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo "<?php"; ?> namespace <?php echo preg_replace('#\\\\[^\\\\]*$#', "", $this->class_name); ?>;

    <?php if (is_abstract($this->class_name)) echo "abstract"; ?> class <?php echo preg_replace('#^([^\\\\]+\\\\)*#', "", $this->class_name) ?> extends <?php echo $this->must_extend; ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>