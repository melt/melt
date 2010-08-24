<?php namespace nmvc\mail;

/**
 * nanoMVC wrapper class for sending RFC compatible e-mails
 * platform compatible and without relying on wrappers.
 */
class Mailer {
    const RFC_MAX_LINELENGTH = 950;

    /** @var Address Where mailing from, set by configuration by default. */
    public $from;
    /** @var Address Optional reply-to address. */
    public $reply_to;
    /** @var AddressList Where to send mail. Mail must have at least one repicent. */
    public $to;
    /**
    * @var AddressList List of CC repicents. */
    public $cc;
    /** @var AddressList List of BCC repicents. */
    public $bcc;
    
    public function __construct() {
        $this->from = new Address();
        $this->from->set(config\FROM_ADDRESS, config\FROM_NAME);
        $this->reply_to = new Address();
        $this->to = new AddressList();
        $this->cc = new AddressList();
        $this->bcc = new AddressList();
    }

    public $smtp_from_host = config\SMTP_FROM_HOST;
    /** @var string Host name to send mail to. Default is config value. */
    public $smtp_host = config\SMTP_HOST;
    /** @var integer Host port to send mail to. Default is config value. */
    public $smtp_port = config\SMTP_PORT;
    /** @var integer Timeout when connecting to SMTP host. Default is config value. */
    public $smtp_timeout = config\SMTP_TIMEOUT;
    /** @var boolean True to enable SMTP authentication. Default is config value. */
    public $smtp_auth_enable = config\SMTP_AUTH_ENABLE;
    /** @var string Username to use with SMTP authentication. Default is config value. */
    public $smtp_auth_user = config\SMTP_AUTH_USER;
    /** @var string Password to use with SMTP authentication. Default is config value. */
    public $smtp_auth_password = config\SMTP_AUTH_PASSWORD;

    private function addressEmail() {
        // If from is not set directly then use config.
        if ($this->from->email == null) {
            if (config\FROM_ADDRESS != "") {
                $this->from->email = config\FROM_ADDRESS;
            } else {
                // Use INI configured from as a last resort.
                $this->from->email = ini_get('sendmail_from');
                if ($this->from->email == "")
                    trigger_error("Mailer was told to send with no configured from address. (Not set directly, in config or in ini.)", \E_USER_ERROR);
            }
            $this->from->name = config\FROM_NAME;
        }
        // Setting this in ini is required to send mail correctly.
        $headers = "From: " . $this->from->getAddress() . Smtp::CRLF;
        ini_set('sendmail_from', $this->from->email);
        if ($this->to->count() == 0)
            trigger_error("No repicents given!", \E_USER_ERROR);
        $headers .= $this->cc->getAsHeader('Cc', true);
        $headers .= $this->bcc->getAsHeader('Bcc', true);
        if ($this->reply_to->email != null) {
            $reply_to = $this->reply_to->getAddress();
            $headers .= "Reply-To: $reply_to" . Smtp::CRLF;
            $headers .= "Return-Path: $reply_to" . Smtp::CRLF;
        }
        return $headers;
    }

    /** Sends this mail as plain text. */
    public function mailPlain($body, $subject = null) {
        $headers = $this->addressEmail();
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . Smtp::CRLF;
        // Base 64 encode content and put it in a single blob.
        $headers .= 'Content-transfer-encoding: base64' . Smtp::CRLF;
        $body = base64_encode($body);
        // Remove whitespace from start and end of rows, and cut rows to a length of 998.
        $rows_out = array();
        $rows_in = explode("\n", $body);
        foreach ($rows_in as $row) {
            $row = trim($row);
            $i = 0;
            while (true) {
                $r = substr($row, $i, 998);
                $rows_out[] = $r;
                $i += 998;
                if ($i >= strlen($row))
                    break;
            }
        }
        // Use \r\n linebreaks.
        $body = implode($rows_out, "\r\n");
        // Send the mail.
        $this->doMail($subject, $body, $headers);
    }

    /** Sends this mail with HTML content. */
    public function mailHTML($body, $subject = null) {
        $headers = $this->addressEmail();
        // Assemble the content.
        $html_subject = ($subject == null)? 'Untitled': escape($subject);
        $content = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\r\n \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\r\n";
        $content = "<html>\r\n\t<head>\r\n\t\t<title>$html_subject\r\n\t</title>\r\n\t</head>\r\n\t<body>\r$body\r\n\t</body>\r\n</html>\r\n";
        // Create a plain text fallback for the HTML content.
        $content = $this->createPlainTextFallback($content, $headers);
        // Send the mail.
        $this->doMail($subject, $content, $headers);
        return;
    }

    private function doMail($subject, $content, $headers) {
        // Also append other standard headers.
        $headers .= 'MIME-Version: 1.0' . Smtp::CRLF;
        $headers .= 'X-Mailer: nanoMVC/' . \nmvc\internal\VERSION . '; PHP/' . phpversion() . Smtp::CRLF;
        $headers .= 'Date: ' . date("r") . Smtp::CRLF;
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=" . Smtp::CRLF;
        $headers .= $this->to->getAsHeader('To');
        $headers .= $this->cc->getAsHeader('Cc');
        // Compile RCPT array.
        $rcpt_array = array();
        foreach ($this->to->getPlainArray() as $rcpt)
            $rcpt_array[$rcpt] = 1;
        foreach ($this->cc->getPlainArray() as $rcpt)
            $rcpt_array[$rcpt] = 1;
        foreach ($this->bcc->getPlainArray() as $rcpt)
            $rcpt_array[$rcpt] = 1;
        if (count($rcpt_array) == 0)
            trigger_error(__CLASS__ . " failed, no repicents specified!", \E_USER_ERROR);
        $rcpt_array = array_keys($rcpt_array);
        foreach ($rcpt_array as $rcpt_email) {
            if (!\nmvc\string\email_validate($rcpt_email))
                trigger_error(__CLASS__ . " failed, invalid repicent email address: $rcpt_email", \E_USER_ERROR);
        }
        // Read FROM.
        $from_email = $this->from->email;
        if (!\nmvc\string\email_validate($from_email))
            trigger_error(__CLASS__ . " failed, invalid from address: $from_email", \E_USER_ERROR);
        // Read smtp 'from' host.
        $smtp_from_host = $this->smtp_from_host;
        if ($smtp_from_host == null)
            $smtp_from_host = gethostname();
        // Compile data.
        $data = $headers . Smtp::CRLF . $content;
        // Connect to SMTP server and send the mail.
        $smtp = new Smtp();
        $smtp->Connect($this->smtp_host, $this->smtp_port, $this->smtp_timeout)
            or trigger_error(__CLASS__ . " failed, could not connect to SMTP host " . $this->smtp_host . ":" . $this->smtp_port . "! (Timeout is " . $this->smtp_timeout. " seconds). Message: " . $smtp->error, \E_USER_ERROR);
        $smtp->Hello($smtp_from_host)
            or trigger_error(__CLASS__ . " failed, HELO/EHLO command error. Message: " . $smtp->error, \E_USER_ERROR);
        if ($this->smtp_auth_enable) {
            $smtp->Authenticate($this->smtp_auth_user, $this->smtp_auth_password)
                or trigger_error(__CLASS__ . " failed, authentication error. Message: " . $smtp->error, \E_USER_ERROR);
        }
        $smtp->Mail($from_email)
            or trigger_error(__CLASS__ . " failed, MAIL command error. Message: " . $smtp->error, \E_USER_ERROR);
        foreach ($rcpt_array as $rcpt_email) {
            $smtp->Recipient($rcpt_email)
                or trigger_error(__CLASS__ . " failed, RCPT command error. Message: " . $smtp->error, \E_USER_ERROR);
        }
        $smtp->Data($data)
            or trigger_error(__CLASS__ . " failed, DATA command error. Message: " . $smtp->error, \E_USER_ERROR);
        $smtp->Quit(true)
            or trigger_error(__CLASS__ . " failed, QUIT command error. Message: " . $smtp->error, \E_USER_ERROR);
    }

    /**
     * Escapes a HTML message and sends it as both plain text and HTML,
     * using a heuristic algoritm to convert the message.
     * The plain-text variant is just a safe fallback for clients that doesn't
     * support HTML.
     */
    private function createPlainTextFallback($html, &$headers) {
        $boundary = \nmvc\string\random_alphanum_str(16);
        $headers .= "Content-Type: multipart/alternative; boundary=$boundary" . Smtp::CRLF;
        // Set of regex whitespace not including newline.
        $wnn = '[\x00-\x09\x0B-\x20]';
        $plain_text = $html;
        // Escape styles from html.
        $plain_text = preg_replace("#<style[^>]*>[^<]*#", "", $plain_text);
        // Escape anchors from html.
        $plain_text = preg_replace('#href[ ]*="[ ]*(https?://[^"]+)"#', ">$1 <foo", $plain_text);
        // Strip markup from html.
        $plain_text = trim(strip_tags($plain_text));
        // Escape entities from html.
        $plain_text = html_entity_decode($plain_text, \ENT_QUOTES, "UTF-8");
        // Remove unnecessary number of newlines.
        $plain_text = preg_replace("#\n$wnn+\n[\s]+#", "\n\n", $plain_text);
        // Remove traling and prefixing space.
        $plain_text = preg_replace("#\n$wnn+|$wnn+\n#", "\n", $plain_text);
        // Remove unnecessary blank spaces.
        $plain_text = preg_replace("#[ ][ ]+#", " ", $plain_text);
        // Compile plain text part.
        $plain_text_part = "--$boundary\n"
        . "Content-Type: text/plain; charset=UTF-8\n"
        . "Content-Transfer-Encoding: base64\n\n"
        . chunk_split(base64_encode($plain_text), self::RFC_MAX_LINELENGTH, smtp::CRLF);
        // Compile HTML part.
        $html_part = "--$boundary\n"
        . "Content-Type: text/html; charset=UTF-8\n"
        . "Content-Transfer-Encoding: base64\n\n"
        . chunk_split(base64_encode($html), self::RFC_MAX_LINELENGTH, smtp::CRLF);
        // Forge content.
        $content = "$plain_text_part\n\n$html_part\n\n--$boundary--";
        return $content;
    }

}

