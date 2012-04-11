<?php namespace melt\js; ?>
<?php $this->layout->enterSection("head_head"); ?>
    <script type="text/javascript" src="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/jquery-autocomplete/jquery.autocomplete.min.js"): url("/static/cmod/js/jquery-autocomplete/jquery.autocomplete.js"); ?>"></script>
    <script type="text/javascript" src="<?php echo url("/static/cmod/js/jquery-autocomplete/jquery.select-autocomplete.js"); ?>"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo url("/static/cmod/js/jquery-autocomplete/jquery.autocomplete.css"); ?>" />
<?php $this->layout->exitSection(); ?>