<?php namespace nanomvc\nano_cms; ?>
<?php $interface = new \nanomvc\qmi\ModelInterface(url(REQ_URL)); ?>
    <?php \nanomvc\qmi\print_interface($interface->getComponents($this->site_node, array(
        "title" => "Title:",
    ))); ?>
<?php $interface->finalize(true, true); ?>