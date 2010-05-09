<?php namespace nmvc\ctrl; ?>
<?php echo $this->view("layout"); ?>
<h1>Ctrl Settings</h1>
<p>
    <?php $mif = new \nmvc\qmi\ModelInterface(); ?>
        <?php \nmvc\qmi\print_interface($mif->getComponents(
            CtrlSettingsModel::get(),
            array(
                "wysiwyg_type" => "Text Editor Layout:"
            )
        )); ?>
    <?php $mif->finalize(true); ?>
</p>