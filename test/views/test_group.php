<?php namespace nmvc; ?>
<?php $this->layout->enterSection("test_outcomes"); ?>
    <h2><?php echo $this->name ?> (<?php echo $this->ms; ?> ms)</h2>
    <ul class="test_group_results">
        <?php foreach ($this->test_outcomes as $test_outcome): ?>
            <li class="test_outcome">
                <?php if ($test_outcome->success): ?>
                    <span class="pass"><?php echo $test_outcome->name; ?> passed in <?php echo $test_outcome->ms; ?> ms</span>
                <?php else: ?>
                    <span class="fail" onclick="javascript: $(this).find('~ iframe').toggle();">
                        <?php echo $test_outcome->name; ?> failed in <?php echo $test_outcome->ms; ?> ms,
                        <?php echo $test_outcome->reason;?>
                    </span>
                    <iframe style="width: 90%; height: 500px" marginwidth="0" marginheight="0" vspace="0" hspace="0" class="associated_data" src="data:text/html;base64,<?php echo \base64_encode($test_outcome->data); ?>"></iframe>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php $this->layout->exitSection(); ?>