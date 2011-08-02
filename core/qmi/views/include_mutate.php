<?php namespace melt\qmi; ?>
<?php $this->layout->enterSection("head"); ?>
    <script type="text/javascript">
        var QMI_MUTATE_URL = <?php echo \json_encode(url("/qmi/actions/mutate")); ?>;
        var QMI_ERROR_STATE_INVALID_CONFIRM = <?php echo \melt\string\quote(_("Error: The session timed out or the object you where editing was deleted. Do you want to reload the page? (All changes made will be lost.)")); ?>;
    </script>
    <script type="text/javascript" src="<?php echo url("/static/cmod/qmi/mutate.js"); ?>"></script>
<?php $this->layout->exitSection(); ?>


