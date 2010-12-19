<?php namespace nmvc\mail;

class MailModule extends \nmvc\CoreModule {
    public static function beforeRequestProcess() {
        parent::beforeRequestProcess();
        if (REQ_IS_CORE_DEV_ACTION)
            return;
    }
}


