<?php namespace nanomvc\ctrl; ?>
<?php $this->layout->enterSection("head"); ?>
<title>Ctrl Administration</title>
<link rel="stylesheet" type="text/css" href="<?php echo url("/static/mod/ctrl/style.css"); ?>" media="screen" />
<style type="text/css">
    ul#menu > li > a[href^="<?php echo url(REQ_URL); ?>"] {
        background-color: #dfdfdf;
        font-weight: bold;
    }
</style>
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("body_head"); ?>
<div id="wrapper">
    <?php $this->view("main_menu"); ?>
    <div class="content" id="content">
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("body_foot"); ?>
    </div>
</div>
<?php $this->layout->exitSection(); ?>