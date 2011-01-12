<?php namespace nmvc\core; ?>
<p><code>
    __File: <?php echo $this->path; ?>
</code></p>
<p>
    All nanoMVC files is required by convention to <em>exactly</em> begin with
    the well defined prefix
    <code><?php echo escape("<?php"); ?> namespace nmvc[\<i>module name</i>];</code>
    This prefix enables parsers to quickly determine if this is a nanoMVC
    class/view or not, and conventions improve code readability.
    This prefix was found to either wrong or missing in this file.
</p>
<h2>Suggested Fix:</h2>
<p>
    There could be multiple causes of this error. First check that the first
    line of the file starts <em>exactly</em> like this:
</p>
<p>
    <?php $this->layout->enterSection("code"); ?>
    <?php echo $this->prefix; ?>
    <?php $this->layout->exitSection(); ?>
    <?php highlight_string($this->code); ?>
</p>
<p>
    Make sure your text-editor is set to UTF-8 encoding
    and doesn't insert a Byte Order Marker or other binary junk at the
    beginning of the file.
</p>