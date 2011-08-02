<?php namespace melt\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    The file declared the class <code><?php echo $this->class_name; ?></code>
    and like all Melt Framework class declarations, it is required to include a
    namespace declaration on the first line of the file. Melt Framework however
    failed to find this namespace declaration when parsing the file during
    blank overrideble class generation.
</p>
<h2>Suggested Fix:</h2>
<p>
    Include the namespace declaration like:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo "<?php"; ?> namespace <?php echo \preg_replace('#\\\\[^\\\\]*$#', "", $this->class_name); ?>;

    class <?php echo \preg_replace('#^([^\\\\]+\\\\)*#', "", $this->class_name) ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>