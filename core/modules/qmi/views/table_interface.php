<?php namespace nmvc\qmi; ?>
<div class="qmi_table_interface">
    <table>
        <?php foreach ($this->components as $name => $component): ?>
            <tr>
                <td class="qmi_label">
                    <p>
                        <label for="<?php echo $component->id; ?>">
                            <?php echo escape($component->label); ?>
                        </label>
                    </p>
                </td>
                <td class="qmi_component">
                    <div class="c_<?php echo $name; ?>">
                        <?php echo $component->html_interface; ?>
                    </div>
                    <?php if (isset($component->html_error)): ?>
                        <div class="qmi_error">
                            <?php echo $component->html_error; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>