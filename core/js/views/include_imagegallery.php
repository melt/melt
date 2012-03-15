<?php namespace melt\js; ?>
<?php $this->layout->enterSection("head_head"); ?>
    <link type="text/css" href="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap-imagegallery/css/bootstrap-image-gallery.css"): url("/static/cmod/js/twitter-bootstrap-imagegallery/css/bootstrap-image-gallery.min.css"); ?>" rel="stylesheet" />
    <script type="text/javascript" src="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap-imagegallery/js/bootstrap-image-gallery.js"): url("/static/cmod/js/twitter-bootstrap-imagegallery/js/bootstrap-image-gallery.min.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>