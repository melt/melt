<?php namespace melt\core; ?>
<?php if (config\DISPLAY_DEVMODE_NOTICE): ?>
    <?php $this->layout->enterSection("content_head"); ?>
    <div onclick="javascript: this.style.display = 'none';" style="position: fixed; bottom: 0px; right: 0px; width: 230px; padding: 5px; cursor: pointer; border: 1px solid black; background-color: white; color: black; font: 10px verdana; text-align: center; font-weight: normal; text-shadow: none;">
        Currently in developer mode.<br />Application is closed to visitors.
    </div>
    <?php $this->layout->exitSection(); ?>
<?php endif; ?>