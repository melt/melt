<?php namespace nmvc\mail;

// nanoMVC wrapper class for sending RFC compatible e-mails.
class Mailer {
    /** @var Address */
    public $from;
    /** @var Address */
    public $reply_to;
    /** @var AddressList */
    public $to;
    /**
    * @desc Note, names will be discarded in cc due to weak mail() implementation in php that does not support this.
    * @var AddressList */
    public $cc;
    /** @var AddressList */
    public $bcc;
    public function __construct() {
        $this->from = new Address();
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
        $headers = "From: " . $this->from->getAddress() . PHP_EOL;
        ini_set('sendmail_from', $this->from->email);
        if ($this->to->count() == 0)
            trigger_error("No repicents given!", \E_USER_ERROR);
        $headers .= $this->cc->getAsHeader('Cc', true);
        $headers .= $this->bcc->getAsHeader('Bcc', true);
        if ($this->reply_to->email != null) {
            $reply_to = $this->reply_to->getAddress();
            $headers .= "Reply-To: $reply_to" . PHP_EOL;
            $headers .= "Return-Path: $reply_to" . PHP_EOL;
        }
        return $headers;
    }

    /** Sends this mail as plain text. */
    public function mailPlain($body, $subject = null) {
        $headers = $this->addressEmail();
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . PHP_EOL;

        // Base 64 encode content and put it in a single blob.
        $headers .= 'Content-transfer-encoding: base64' . PHP_EOL;
        $content = base64_encode($content);

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
        $headers .= 'MIME-Version: 1.0' . PHP_EOL;
        $headers .= 'X-Mailer: nanoMVC/' . \nmvc\VERSION . '; PHP/' . phpversion() . PHP_EOL;
        $headers .= 'Date: ' . date("r") . PHP_EOL;

        // Verify that INI settings are correct.
        $smtp = strtolower(ini_get('smtp'));
        $trg = strtolower(config\SMTP_HOST);
        if ($smtp != $trg)
            if (false === ini_set('SMTP', $trg))
                trigger_error("The SMTP server '".$trg."' could not be set into configuration!", \E_USER_ERROR);
        // add_x_header is a potential security risk, disable.
        ini_set('mail.add_x_header', 'Off');

        // Use MIME encoded-word syntax to transmit UTF-8 subject.
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        if (strpos(PHP_OS, "WIN") !== false) {
            // Windows implementation uses >to< argument to speak directly with SMTP servers.
            $to = $this->to->getPlainList();
            // To preserve the name formating, we need to insert this header ourselves.
            if (strpos(PHP_OS, "WIN") !== false)
                $headers .= $this->to->getAsHeader('To');
        } else
            // UNIX implementation constructs it's own "to" headers.
            $to = $this->to->getList();
        // Finaly mail it.
        if (FALSE === mail($to, $subject, $content, $headers))
            trigger_error("Mailer: The mail could not be sent, mail() returned error.");
    }

    /**
     * Escapes a HTML message and sends it as both plain text and HTML,
     * using a heuristic algoritm to convert the message.
     * The plain-text variant is just a safe fallback for clients that doesn't
     * support HTML.
     */
    private function createPlainTextFallback($html, &$headers) {
        $boundary = "------=_NextPart_" . \nmvc\string\random_alphanum_str(16);
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"" . PHP_EOL;
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
        . base64_encode($plain_text);
        // Compile HTML part.
        $html_part = "--$boundary\n"
        . "Content-Type: text/html; charset=UTF-8\n"
        . "Content-Transfer-Encoding: base64\n\n"
        . base64_encode($html);
        // Forge content.
        $content = "$plain_text_part\n\n$html_part\n\n--$boundary--";
        return $content;
    }

}

