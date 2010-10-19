<?php namespace nmvc\qmi; ?>
<?php foreach ($this->html_components as $comp_name => $component): ?>
    <?php list($interface, $html_id) = $component; ?>
    <p>
        <label for="<?php echo $html_id; ?>">
            <?php echo escape(@$this->labels[$comp_name]); ?>
        </label>
    </p>
    <div class="c_<?php echo $comp_name; ?>">
        <?php echo $interface; ?>
    </div>
<?php endforeach; ?>