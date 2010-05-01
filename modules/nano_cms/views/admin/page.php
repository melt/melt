<?php namespace nanomvc\nano_cms; ?>
<?php $interface = new \nanomvc\qmi\ModelInterface(url("/nano_cms/admin/pages/{id}")); ?>
    <?php \nanomvc\qmi\print_interface($interface->getComponents($this->site_node, array(
        "title" => "Title:",
        "content" => "Content:",
    ))); ?>
<?php $interface->finalize(true, true); ?>