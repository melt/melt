<?php namespace nmvc\core; ?>
<?php $this->display("/core/developer/error_layout"); ?>
<h1>
    Access Denied
</h1>
<p>
    Authorization as an application developer with the developer key
    is required to continue:
</p>
<form action="<?php echo url("/core/action/set_key"); ?>" method="post">
    <p>
        <input type="password" name="devkey" />
        <input type="submit" name="session" value="Session" />
        <input type="submit" name="day" value="Day" />
        <input type="submit" name="year" value="Year" />
    </p>
</form>
<p>
    Please refer to the
    <a href="http://nanomvc.com/documentation">nanomvc documentation</a>
    for more information.
</p>
