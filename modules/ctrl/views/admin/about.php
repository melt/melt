<?php namespace nanomvc\ctrl; ?>
<?php echo $this->view("layout"); ?>
<h1>About ctrl</h1>
<p>
    You are the proud owner of ctrl, a nanoMVC module that provides abstract
    administration for other nanoMVC modules.
</p>
<p>
    <strong>You are using version <?php echo CtrlModule::getVersion(); ?> </strong>
</p>
<p>Logo:</p>
<img src="<?php echo url("/static/mod/ctrl/ctrl.png"); ?>" alt="ctrl Logo" />