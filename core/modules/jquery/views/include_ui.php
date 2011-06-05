<?php namespace nmvc\jquery; ?>
<?php $this->layout->enterSection("head"); ?>
    <?php if ($this->theme[0] == "/"): ?>
        <link type="text/css" href="<?php echo url($this->theme); ?>" rel="stylesheet" />
    <?php else: ?>
        <link type="text/css" href="<?php echo url("/static/cmod/jquery/jquery-ui-themes/" . $this->theme . "/jquery-ui.css"); ?>" rel="stylesheet" />
    <?php endif; ?>
    <script type="text/javascript" src="<?php echo url("/static/cmod/jquery/jquery-ui.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>