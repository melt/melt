<?php namespace nanomvc\jquery; ?>
<?php $this->layout->enterSection("head"); ?>
<link type="text/css" href="<?php echo url("/static/mod/jquery/jquery-ui-themes/" . $this->theme . "/jquery-ui.css"); ?>" rel="stylesheet" />
<script type="text/javascript" src="<?php echo url("/static/mod/jquery/jquery-ui.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>