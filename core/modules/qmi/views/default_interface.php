<?php namespace nmvc\qmi; ?>
<div class="qmi_default_interface">
    <?php foreach ($this->components as $name => $component): ?>
        <div class="qmi_component">
            <p>
                <label for="<?php echo $component->id; ?>">
                    <?php echo escape($component->label); ?>
                </label>
            </p>
            <div class="c_<?php echo $name; ?>">
                <?php echo $component->html_interface; ?>
            </div>
            <?php if (isset($component->html_error)): ?>
                <div class="qmi_error">
                    <?php echo $component->html_error; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>