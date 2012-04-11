<?php namespace melt\messenger; ?>
<script type="text/javascript">
    $(function() {
        window.setTimeout(function() {
            $("#<?php echo $this->uuid("dialog"); ?>").alert('close');
        }, 3000);
    });
</script>
<div id="<?php echo $this->uuid("dialog"); ?>" class="messenger-alert alert alert-<?php echo ($this->status == "bad")? _("danger"): _("success");?> fade in">
    <a class="close" data-dismiss="alert" href="#">&times;</a>
    <?php echo $this->message; ?>
</div>