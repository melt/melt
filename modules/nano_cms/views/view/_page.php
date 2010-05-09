<?php namespace nmvc\nano_cms; ?>
<?php if (strlen(config\FRAME_VIEW) > 0): ?>
    <?php $this->element(config\FRAME_VIEW); ?>
<?php endif; ?>
<?php echo $this->page->content; ?>