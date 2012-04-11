<?php namespace melt\js; ?>
<?php $this->layout->enterSection("head_head"); ?>
    <script type="text/javascript" src="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap-bootbox/bootbox.js"): url("/static/cmod/js/twitter-bootstrap-bootbox/bootbox.min.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>