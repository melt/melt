<?php namespace nmvc\mail;

abstract class SpooledMailModel_app_overrideable extends \nmvc\AppModel {
    public $from_email = array('core\TextType', 128);
    public $rcpt_list = array('core\SerializedType');

    public $mail_data = array('core\TextType');
    
    public $smtp_host = array('core\TextType', 128);
    public $smtp_auth_enable = array('core\BooleanType');
    public $smtp_auth_user = array('core\TextType', 128);
    public $smtp_auth_password = array('core\TextType', 128);
    public $smtp_port = array('core\IntegerType');
    public $smtp_timeout = array('core\IntegerType');
    public $smtp_from_host = array('core\TextType', 128);

    public $next_attempt = array('core\TimestampType');
    public $last_failure = array('core\TextType', 128);

    protected function beforeStore($is_linked) {
        parent::beforeStore($is_linked);
        if (!$is_linked)
            $this->next_attempt = 1;
    }

    private final static function selectReadyMail() {
        return SpooledMailModel::select()->where("next_attempt")
        ->isntMoreThan(time());
    }

    public final static function processMailQueue($check_first = true) {
        if (\nmvc\core\req_is_fork()) {
            \nmvc\db\enter_critical_section(__FUNCTION__);
            // Process mail queue.
            foreach (self::selectReadyMail() as $spooled_mail)
                $spooled_mail->sendMail();
            \nmvc\db\exit_critical_section(__FUNCTION__);
        } else {
            // Cancel if mail queue is alredy beeing proccessed.
            if (!\nmvc\db\enter_critical_section(__FUNCTION__, 0))
                return;
            // Check if there are any mail ready to send.
            $mail_ready = $check_first? (self::selectReadyMail()->limit(1)->count() != 0): true;
            \nmvc\db\exit_critical_section(__FUNCTION__);
            if (!$mail_ready)
                return;
            // Fork child and send mail.
            \nmvc\core\fork(array('nmvc\mail\SpooledMailModel', __FUNCTION__));
        }
    }

    public function sendMail() {
        try {
            // Evaluate smtp 'from' host.
            $smtp_from_host = $this->smtp_from_host;
            if ($smtp_from_host == null)
                $smtp_from_host = \gethostname();
            // Connect to SMTP server and send the mail.
            $smtp = new Smtp();
            $smtp->Connect($this->smtp_host, $this->smtp_port, $this->smtp_timeout);
            if (!$smtp->Connected())
                throw new \Exception(__CLASS__ . " failed, could not connect to SMTP host " . $this->smtp_host . ":" . $this->smtp_port . "! (Timeout is " . $this->smtp_timeout. " seconds). Message: " . var_export($smtp->error, true));
            if (!$smtp->Hello($smtp_from_host))
                throw new \Exception(__CLASS__ . " failed, HELO/EHLO command error. Message: " . var_export($smtp->error, true));
            if ($this->smtp_auth_enable) {
                if (!$smtp->Authenticate($this->smtp_auth_user, $this->smtp_auth_password))
                    throw new \Exception(__CLASS__ . " failed, authentication error. Message: " . var_export($smtp->error, true));
            }
            if (!$smtp->Mail($this->from_email))
                throw new \Exception(__CLASS__ . " failed, MAIL command error. Message: " . var_export($smtp->error, true));
            foreach ($this->rcpt_list as $rcpt_email) {
                if (!$smtp->Recipient($rcpt_email))
                    throw new \Exception(__CLASS__ . " failed, RCPT command error. Message: " . var_export($smtp->error, true));
            }
            if (!$smtp->Data($this->mail_data))
                throw new \Exception(__CLASS__ . " failed, DATA command error. Message: " . var_export($smtp->error, true));
            // It might seem unnessecary to throw an exception on failed
            // quit but the mailer NEED to make sure the mail has been sent.
            if (!$smtp->Quit(true))
                throw new \Exception(__CLASS__ . " failed, QUIT command error. Message: " . var_export($smtp->error, true));
            // Mail was successfully sent, done.
            $this->unlink();
        } catch (\Exception $ex) {
            $this->last_failure = $ex->getMessage();
            $this->next_attempt = $this->next_attempt + config\SPOOL_RETRY_INTERVAL_SECONDS;
            $this->store();
        }
    }
}