<?php

namespace nanomvc\mail;

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
            if (CONFIG::$email_address != "") {
                $this->from->email = CONFIG::$email_address;
            } else {
                // Use INI configured from as a last resort.
                $this->from->email = ini_get('sendmail_from');
                if ($this->from->email == "")
                    trigger_error("Mailer was told to send with no configured from address. (Not set directly, in config or in ini.)", \E_USER_ERROR);
            }
            $this->from->name = CONFIG::$email_name;
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

    /**
    * @desc Sends this mail as plain text.
    */
    public function mailPlain($body, $subject = null) {
        $headers = $this->addressEmail();
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . PHP_EOL;

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

    /**
    * @desc Sends this mail as XHTML valid content.
    */
    public function mailXHTML($body, $subject = null) {
        $headers = $this->addressEmail();
        $headers .= 'Content-Type: text/html; charset=UTF-8' . PHP_EOL;

        // Assemble the content.
        $html_subject = ($this->subject == null)? 'Untitled': api_html::escape($subject);
        $html_body = api_html::escape($body);
        $content = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\r\n \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\r\n";
        $content = "<html>\r\n\t<head>\r\n\t\t<title>$html_subject\r\n\t</title>\r\n\t</head>\r\n\t<body>\r\$html_body\r\n\t</body>\r\n</html>\r\n";

        // Send the mail.
        $this->doMail($subject, $content, $headers);
        return;
    }

    private function doMail($subject, $content, $headers) {
        // Also append standard bulk/auto generated indicator headers.
        $headers .= "Precedence: bulk" . PHP_EOL;
        $headers .= "Auto-submitted: auto-generated" . PHP_EOL;

        // Also append other standard headers.
        $headers .= 'MIME-Version: 1.0' . PHP_EOL;
        $headers .= 'X-Mailer: nanoMVC/' . nmvc_version . '; PHP/' . phpversion() . PHP_EOL;
        $headers .= 'Date: ' . date("r") . PHP_EOL;

        // Verify that INI settings are correct.
        $smtp = strtolower(ini_get('smtp'));
        $trg = strtolower(CONFIG::$email_smtp);
        if ($smtp != $trg)
            if (false === ini_set('SMTP', $trg))
                trigger_error("VMAIL: The SMTP server '".$trg."' could not be set into configuration!", \E_USER_ERROR);
        // add_x_header is a potential security risk, disable.
        ini_set('mail.add_x_header', 'Off');

        // Use MIME encoded-word syntax to transmit UTF-8 subject.
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        // Base 64 encode content and put it in a single blob.
        $content = base64_encode($content);
        $headers .= 'Content-transfer-encoding: base64' . PHP_EOL;

        
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

}

