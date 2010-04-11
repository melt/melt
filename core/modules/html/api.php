<?php

namespace nanomvc\html;

/** Echos a complete xhtml 1.1 document. */
function write($head, $body) {
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head>';
    echo $head;
    echo '<meta http-equiv="Content-type" content="text/html; charset=UTF-8" /></head><body>';
    echo $body;
    echo '</body></html>';
}

/** Escapes given string so it can be safely printed in HTML. */
function escape($string) {
    return htmlspecialchars($string, ENT_COMPAT, 'UTF-8');
}

/** Decodes given HTML string to UTF-8. */
function decode($html) {
    return html_entity_decode($html, ENT_COMPAT, 'UTF-8');
}

// Import some functions to the global namespace.
include __DIR__ . "/imports.php";