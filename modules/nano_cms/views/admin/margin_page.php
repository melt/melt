<?php namespace nanomvc\nano_cms; ?>
<?php $interface = new \nanomvc\qmi\ModelInterface(url(REQ_URL)); ?>
    <?php \nanomvc\qmi\print_interface($interface->getComponents($this->site_node, array(
        "title" => "Title:",
        "content" => "Content:",
    ))); ?>
<input type="button" value="Delete" onclick="javascript: if (confirm('Are you sure?')) document.location = '<?php echo \nanomvc\qmi\get_action_link($this->site_node, "delete"); ?>';" />
<?php $interface->finalize(); ?>