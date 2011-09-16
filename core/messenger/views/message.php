<?php namespace melt\messenger; ?>
<?php $this->layout->enterSection("content_head"); ?>
    <?php $this->display("/messenger/raw_message"); ?>
<?php $this->layout->exitSection(); ?>