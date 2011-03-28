<?php namespace nmvc\mail;

abstract class SpooledMailModel_app_overrideable extends \nmvc\AppModel {
    public $from_email = array('core\TextType', 128);
    public $rcpt_list = array('core\SerializedType');

    public $mail_data = array('core\TextType');

    public $last_attempt = array('core\TimestampType');
    public $next_attempt = array('core\TimestampType');
    public $last_failure = array('core\TextType', 256);

    protected function beforeStore($is_linked) {
        parent::beforeStore($is_linked);
        if (!$is_linked)
            $this->next_attempt = 1;
    }

    private final static function selectReadyMail() {
        return SpooledMailModel::select()->where("next_attempt")
        ->isntMoreThan(time());
    }

    const MUTEX_PROCESS_MAIL_QUEUE = 'mail\process_mail_queue';

    public final static function processMailQueue($check_first = true) {
        // Cancel if mail queue is alredy beeing proccessed
        // (prevents beeing blocked when checking).
        if (!\nmvc\db\enter_critical_section(self::MUTEX_PROCESS_MAIL_QUEUE, 0))
            return;
        // Check if there are any mail ready to send.
        $mail_ready = $check_first? (self::selectReadyMail()->limit(1)->count() > 0): true;
        \nmvc\db\exit_critical_section(self::MUTEX_PROCESS_MAIL_QUEUE);
        if (!$mail_ready)
            return;
        // Do work of sending mail in another thread.
        \nmvc\core\fork(array('nmvc\mail\SpooledMailModel', 'processMailQueueFork'));
    }

    public final static function processMailQueueFork() {
        \nmvc\db\enter_critical_section(self::MUTEX_PROCESS_MAIL_QUEUE);
        // Process mail queue.
        foreach (self::selectReadyMail()->forUpdate() as $spooled_mail)
            $spooled_mail->sendMail();
        \nmvc\db\exit_critical_section(self::MUTEX_PROCESS_MAIL_QUEUE);
    }

    public function sendMail() {
        try {
            // Connect to SMTP server and send the mail.
            $smtp = new Smtp();
            @$smtp->Connect(config\SMTP_HOST, config\SMTP_PORT, config\SMTP_TIMEOUT);
            if (!@$smtp->Connected())
                throw new \Exception(__CLASS__ . " failed, could not connect to SMTP host " . config\SMTP_HOST . ":" . config\SMTP_PORT . "! (Timeout is " . config\SMTP_TIMEOUT . " seconds). Message: " . var_export($smtp->error, true));
            if (!@$smtp->Hello(config\SMTP_FROM_HOST != null? config\SMTP_FROM_HOST: \gethostname()))
                throw new \Exception(__CLASS__ . " failed, HELO/EHLO command error. Message: " . var_export($smtp->error, true));
            if (config\SMTP_TLS_ENABLE) {
                if (!@$smtp->StartTLS())
                    throw new \Exception(__CLASS__ . " failed, TLS startup error. Message: " . var_export($smtp->error, true));
            }
            if (config\SMTP_AUTH_ENABLE) {
                if (!@$smtp->Authenticate(config\SMTP_AUTH_USER, config\SMTP_AUTH_PASSWORD))
                    throw new \Exception(__CLASS__ . " failed, authentication error. Message: " . var_export($smtp->error, true));
            }
            if (!@$smtp->Mail($this->from_email))
                throw new \Exception(__CLASS__ . " failed, MAIL command error. Message: " . var_export($smtp->error, true));
            foreach ($this->rcpt_list as $rcpt_email) {
                if (!@$smtp->Recipient($rcpt_email))
                    throw new \Exception(__CLASS__ . " failed, RCPT command error. Message: " . var_export($smtp->error, true));
            }
            if (!@$smtp->Data($this->mail_data))
                throw new \Exception(__CLASS__ . " failed, DATA command error. Message: " . var_export($smtp->error, true));
            // It might seem unnessecary to throw an exception on failed
            // quit but the mailer NEED to make sure the mail has been sent.
            if (!@$smtp->Quit(true))
                throw new \Exception(__CLASS__ . " failed, QUIT command error. Message: " . var_export($smtp->error, true));
            // Mail was successfully sent, done.
            $this->unlink();
        } catch (\Exception $ex) {
            $this->last_failure = $ex->getMessage();
            $this->last_attempt = time();
            $this->next_attempt = time() + config\SPOOL_RETRY_INTERVAL_SECONDS;
            $this->store();
        }
    }
}