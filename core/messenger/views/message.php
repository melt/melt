<?php namespace melt\messenger; ?>
<?php $this->layout->enterSection("head"); ?>
    <script type="text/javascript">
        $(function() {
            window.setTimeout(function() {
                $("#request-message-dialog").dialog({
                    autoOpen: true,
                    closeOnEscape: true,
                    resizable: false,
                    modal: true,
                    width: 400,
                    buttons: {
                        <?php echo json_encode(_("Ok")); ?>: function() {
                            $(this).dialog("destroy");
                        }
                    }
                });
                $(".ui-widget-overlay").click(function() {
                    $("#request-message-dialog").dialog("destroy");
                });
            }, 50);
        });
    </script>
<?php $this->layout->exitSection(); ?>
<?php $this->layout->enterSection("content_head"); ?>
    <div id="request-message-dialog" title="<?php echo ($this->status == "bad")? _("Error"): _("Message");?>" style="display: none;">
        <div class="dialog_content">
            <div class="message">
                <img alt="" src="<?php echo url("/static/cmod/messenger/" . (($this->status == "bad")? "no.png": "ok.png")); ?>" style="float: left; margin: 0px 8px 8px 0px;" />
                <?php echo $this->message; ?>
            </div>
        </div>
    </div>
<?php $this->layout->exitSection(); ?>