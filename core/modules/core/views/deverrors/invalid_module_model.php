<?php namespace nmvc\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    The file was expected to (but didn't) declare a class
    EITHER <code>abstract</code> with the <em>exact</em> name
    (including casing): 
    <code><?php echo $this->expected_name; ?>_app_overrideable</code>
    OR <code>abstract</code> with the <em>exact</em> name
    (including casing):
    <code><?php echo $this->expected_name; ?></code>
</p>
<p>
    This rule is enforced for module <i>models</i>.
</p>
<h2>Suggested Fix:</h2>
<p>
    Change your current class declaration to something like:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo "<?php"; ?> namespace <?php echo preg_replace('#\\\\[^\\\\]*$#', "", $this->expected_name); ?>;

    abstract class <?php echo preg_replace('#^([^\\\\]+\\\\)*#', "", $this->expected_name . "_app_overrideable") ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>
<p>
    --or--
</p>
<p>
    <?php $this->layout->enterSection("code2"); ?>
    <?php echo "<?php"; ?> namespace <?php echo preg_replace('#\\\\[^\\\\]*$#', "", $this->expected_name); ?>;

    abstract class <?php echo preg_replace('#^([^\\\\]+\\\\)*#', "", $this->expected_name) ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code2); ?>
</p>