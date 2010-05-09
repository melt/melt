<?php namespace nmvc\nano_cms; ?>
<?php $interface = new \nmvc\qmi\ModelInterface(url("/nano_cms/admin/pages/{id}")); ?>
    <?php \nmvc\qmi\print_interface($interface->getComponents($this->site_node, array(
        "title" => "Title:",
        "content" => "Content:",
    ))); ?>
<?php $interface->finalize(true, true); ?>