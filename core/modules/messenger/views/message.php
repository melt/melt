<?php namespace nmvc\messenger; ?>
<?php $this->layout->enterSection("head"); ?>
<script type="text/javascript" src="<?php echo url("/static/cmod/messenger/blend.js"); ?>"></script>
<link rel="stylesheet" type="text/css" href="<?php echo url("/static/cmod/messenger/msg_bar.css"); ?>" />
<script type="text/javascript">
    setTimeout('showBar()', 4500);
    setTimeout('hideBar()', 5000);
    function showBar() {
        // Blend out the flasher from the screen.
        opacity('message_bar', 100, 0, 450);
    }
    function hideBar() {
        // Make sure it's hidden. Even on older browsers.
        var e = document.getElementById('message_bar');
        e.style.visibility = 'hidden';
        e.style.width = 0;
        e.style.height = 0;
    }
</script>
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("content_head"); ?>
<div onclick="this.style.visibility = 'hidden';" class="message_bar msg_bar_status_<?php echo $this->status; ?>" id="message_bar">
    <?php echo $this->message; ?>
</div>
<?php $this->layout->exitSection(); ?>