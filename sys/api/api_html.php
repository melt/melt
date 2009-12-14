<?php

/**
*@desc The html api namespace.
*/
class api_html {
    /** @desc Echos a complete xhtml 1.1 document. */
    public static function write($head, $body) {
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head>';
        echo $head;
        echo '<meta http-equiv="Content-type" content="text/html; charset=UTF-8" /></head><body>';
        echo $body;
        echo '</body></html>';
    }

    /**
    * @desc Escapes given string so it can be safely printed in HTML.
    */
    public static function escape($string) {
        return htmlentities($string, ENT_COMPAT, 'UTF-8');
    }

    /**
    * @desc Decodes given HTML string to UTF-8.
    */
    public static function decode($html) {
        return html_entity_decode($html, ENT_COMPAT, 'UTF-8');
    }
}

?>
