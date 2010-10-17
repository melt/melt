<?php namespace nmvc\qmi2; ?>
<table>
    <?php foreach ($this->html_components as $comp_name => $component): ?>
        <?php list($interface, $html_id) = $component; ?>
        <tr>
            <td>
                <p>
                    <label for="<?php echo $html_id; ?>">
                        <?php echo escape(@$this->labels[$comp_name]); ?>
                    </label>
                </p>
            </td>
            <td class="c_<?php echo $comp_name; ?>">
                <?php echo $interface; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>