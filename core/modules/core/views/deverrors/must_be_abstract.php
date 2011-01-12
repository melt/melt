<?php namespace nmvc\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    The file declared a concrete class with the name
    <code><?php echo $this->class_name; ?></code>.
    However nanoMVC requires this class to be declared abstract by design.
</p>
<h2>Suggested Fix:</h2>
<p>
    Change your current class declaration so it includes the abstract keyword:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo "<?php"; ?> namespace <?php echo \preg_replace('#\\\\[^\\\\]*$#', "", $this->class_name); ?>;

    abstract class <?php echo \preg_replace('#^([^\\\\]+\\\\)*#', "", $this->class_name) ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>