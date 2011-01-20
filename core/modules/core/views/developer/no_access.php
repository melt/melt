<?php namespace nmvc\core; ?>
<?php $this->display("/core/developer/error_layout"); ?>
<h1>
    <?php echo _("Access Denied - Function Disabled"); ?>
</h1>
<p>
    <?php echo _("This function can only be accessed by activating maintenance mode and authorizing with the correct developer key."); ?>
</p>
<p>
    <?php echo _("Please refer to the <a %s>NanoMVC Documentation</a> for more information.",'href="http://nanomvc.com/documentation"'); ?>
</p>