<?php namespace melt\mail;

/**
 * Melt Framework wrapper class for sending RFC compatible e-mails
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
    /** @var string Subject of mail. */
    public $subject = "";
    
    public function __construct() {
        $this->from = new Address();
        $this->from->set(config\FROM_ADDRESS, config\FROM_NAME);
        $this->reply_to = new Address();
        $this->to = new AddressList();
        $this->cc = new AddressList();
        $this->bcc = new AddressList();
    }

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

    private $attachments = array();

    public function attachFile($filename, $data) {
        $filename = "\"=?UTF-8?B?" . base64_encode($filename) . "?=\"";
        $this->attachments[] = array("application/octet-stream; name=$filename", $data);
    }

    /** Sends this mail as plain text. */
    public function mailPlain($plain_text) {
        // Assemble the mail and send it.
        $mail_message_content = array(array("text/plain; charset=UTF-8", $plain_text));
        $this->assembleMail($mail_message_content);
    }

    /**
     * Escapes a HTML message and sends it as both plain text and HTML,
     * using a heuristic algoritm to convert the message.
     * The plain-text variant is just a safe fallback for clients that doesn't
     * support HTML.
     */
    private function getPlanTextFallback($html) {
        // Set of regex whitespace not including newline.
        $wnn = '[\x00-\x09\x0B-\x20]';
        $plain_text = $html;
        // Escape styles from html.
        $plain_text = \preg_replace("#<style[^>]*>[^<]*#", "", $plain_text);
        // Escape anchors from html.
        $plain_text = \preg_replace('#href[ ]*="[ ]*(https?://[^"]+)"#', ">$1 <foo", $plain_text);
        // Strip markup from html.
        $plain_text = \trim(strip_tags($plain_text));
        // Escape entities from html.
        $plain_text = \html_entity_decode($plain_text, \ENT_QUOTES, "UTF-8");
        // Remove unnecessary number of newlines.
        $plain_text = \preg_replace("#\n$wnn+\n[\s]+#", "\n\n", $plain_text);
        // Remove traling and prefixing space.
        $plain_text = \preg_replace("#\n$wnn+|$wnn+\n#", "\n", $plain_text);
        // Remove unnecessary blank spaces.
        return \preg_replace("#[ ][ ]+#", " ", $plain_text);
    }

    /** Sends this mail with HTML content. */
    public function mailHTML($html_content) {
        // Create a plain text fallback for the HTML content.
        $plain_text_content = $this->getPlanTextFallback($html_content);
        // Assemble the mail and send it.
        $mail_message_sections = array(
            array("text/plain; charset=UTF-8", $plain_text_content),
            array("text/html; charset=UTF-8", $html_content),
        );
        $this->assembleMail($mail_message_sections);
    }

    private function assembleMail($mail_message_sections) {
        $mail_sections = $this->attachments;
        // Appand message (alternatives) before any attachments.
        \array_unshift($mail_sections, array("multipart/alternative", $mail_message_sections));
        $mail_content = $this->boundaryWrap($mail_sections, "multipart/mixed");
        $this->spoolMail($mail_content);
    }

    private function spoolMail($mail_data) {
        // Also append other standard headers.
        $start_headers = $this->addressEmail();
        $start_headers .= 'MIME-Version: 1.0' . Smtp::CRLF;
        $start_headers .= 'X-Mailer: Melt Framework/' . \melt\internal\VERSION . '; PHP/' . phpversion() . Smtp::CRLF;
        $start_headers .= 'Date: ' . date("r") . Smtp::CRLF;
        $start_headers .= "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=" . Smtp::CRLF;
        $start_headers .= $this->to->getAsHeader('To');
        $start_headers .= $this->cc->getAsHeader('Cc');
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
            if (!\melt\string\email_validate($rcpt_email))
                trigger_error(__CLASS__ . " failed, invalid repicent email address: $rcpt_email", \E_USER_ERROR);
        }
        // Read FROM.
        $from_email = $this->from->email;
        if (!\melt\string\email_validate($from_email))
            trigger_error(__CLASS__ . " failed, invalid from address: $from_email", \E_USER_ERROR);
        // Prepend start headers to mail data.
        $data = $start_headers . $mail_data;
        // Spool the mail.
        $spooled_mail = new SpooledMailModel();
        $spooled_mail->from_email = $from_email;
        $spooled_mail->rcpt_list = $rcpt_array;
        $spooled_mail->mail_data = $data;
        $spooled_mail->store();
    }

    private function boundaryWrap($sections, $multipart_mime) {
        $boundary = \melt\string\random_alphanum_str(16);
        if (\count($sections) < 1) {
            \trigger_error("Boundary wrap called with no sections.", \E_USER_ERROR);
        } else if (\count($sections) == 1) {
            // Skip boundary wrap when only using one section.
            list($content_type, $data) = \reset($sections);
            return \is_array($data)
            ? $this->boundaryWrap($data, $content_type)
            : $this->base64Wrap($data, $content_type);
        }
        $content = "Content-Type: $multipart_mime; boundary=$boundary" . smtp::CRLF . smtp::CRLF;
        foreach ($sections as $section) {
            list($content_type, $data) = $section;
            $content .= "--$boundary" . smtp::CRLF;
            $content .= \is_array($data)
            ? $this->boundaryWrap($data, $content_type)
            : $this->base64Wrap($data, $content_type);
            $content .= smtp::CRLF;
        }
        $content .= "--$boundary--";
        return $content;
    }

    private function base64Wrap($data, $content_type) {
        return "Content-Type: $content_type" . smtp::CRLF
        . "Content-Transfer-Encoding: base64" . smtp::CRLF . smtp::CRLF
        . \chunk_split(\base64_encode($data), self::RFC_MAX_LINELENGTH, smtp::CRLF) . smtp::CRLF;
    }

}

