<?php namespace melt\core; ?>
<?php $this->layout->enterSection("head"); ?>
<title><?php echo _("Service Temporary Unavailible"); ?></title>
<style type="text/css">
    body {
        background-color: #efefef;
    }
    h1, p {
        margin-bottom: 16px;
    }
    div#deverror_message {
        width: 30%;
        margin: 0 auto;
        margin-top: 10%;
        padding: 30px 20px 20px 100px;
        background-color: #ffffff;
        border: 1px solid #afafaf;
        background-image: url(<?php echo url("/static/cmod/core/stop.png"); ?>);
        background-repeat: no-repeat;
        background-position: 40px 30px;
        font: 14px Arial;
    }
</style>
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("content_head"); ?>
<div id="deverror_message">
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("content_foot"); ?>
</div>
<?php $this->layout->exitSection(); ?>
