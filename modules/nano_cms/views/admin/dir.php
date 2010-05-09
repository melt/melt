<?php namespace nmvc\nano_cms; ?>
<?php $interface = new \nmvc\qmi\ModelInterface(url(REQ_URL)); ?>
    <?php \nmvc\qmi\print_interface($interface->getComponents($this->site_node, array(
        "title" => "Title:",
    ))); ?>
<?php $interface->finalize(true, true); ?>