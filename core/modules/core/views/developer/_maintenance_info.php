<?php namespace nmvc\core; ?>
<?php $this->display("/core/developer/error_layout"); ?>
<h1>
    <?php echo _("Temporary Maintenance"); ?>
</h1>
<p>
    <?php echo _("The site is currently offline due to temporary maintenance."); ?>
</p>
<p>
    <?php echo $this->est; ?>
</p>