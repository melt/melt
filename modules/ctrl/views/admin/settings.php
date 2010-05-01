<?php namespace nanomvc\ctrl; ?>
<?php echo $this->view("layout"); ?>
<h1>Ctrl Settings</h1>
<p>
    <?php $mif = new \nanomvc\qmi\ModelInterface(); ?>
        <?php \nanomvc\qmi\print_interface($mif->getComponents(
            CtrlSettingsModel::get(),
            array(
                "wysiwyg_type" => "Text Editor Layout:"
            )
        )); ?>
    <?php $mif->finalize(true); ?>
</p>