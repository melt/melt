<?php namespace melt\js; ?>
<?php $this->layout->enterSection("head_head"); ?>
    <?php if(config\TWITTER_BOOTSTRAP_RESPONSIVE_CSS): ?>
    <link type="text/css" href="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap/css/bootstrap-responsive.css"): url("/static/cmod/js/twitter-bootstrap/css/bootstrap-responsive.min.css"); ?>" rel="stylesheet" />
    <?php else: ?>
    <link type="text/css" href="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap/css/bootstrap.css"): url("/static/cmod/js/twitter-bootstrap/css/bootstrap.min.css"); ?>" rel="stylesheet" />
    <?php endif; ?>
    <script type="text/javascript" src="<?php echo APP_IN_DEVELOPER_MODE? url("/static/cmod/js/twitter-bootstrap/js/bootstrap.js"): url("/static/cmod/js/twitter-bootstrap/js/bootstrap.min.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>