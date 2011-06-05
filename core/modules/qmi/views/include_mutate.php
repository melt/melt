<?php namespace nmvc\qmi; ?>
<?php $this->layout->enterSection("head"); ?>
    <script type="text/javascript">
        const qmi_mutate_url = <?php echo \json_encode(url("/qmi/actions/mutate")); ?>;
        const qmi_error_state_invalid_confirm = <?php echo \nmvc\string\quote(_("Error: The session timed out or the object you where editing was deleted. Do you want to reload the page? (All changes made will be lost.)")); ?>;
    </script>
    <script type="text/javascript" src="<?php echo url("/static/cmod/qmi/mutate.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>


