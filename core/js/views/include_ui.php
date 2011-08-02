<?php namespace melt\js; ?>
<?php $this->layout->enterSection("head_head"); ?>
    <?php if ($this->theme[0] == "/"): ?>
        <link type="text/css" href="<?php echo url($this->theme); ?>" rel="stylesheet" />
    <?php else: ?>
        <link type="text/css" href="<?php echo url("/static/cmod/js/jquery-ui-themes/" . $this->theme . "/jquery-ui.css"); ?>" rel="stylesheet" />
    <?php endif; ?>
    <script type="text/javascript" src="<?php echo url("/static/cmod/js/jquery-ui.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>