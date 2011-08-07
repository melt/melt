<?php namespace melt\html;

/** Escapes given string so it can be safely printed in HTML. */
function escape($string) {
    if ($string instanceof \melt\Type)
        return (string) $string;
    $ret = \htmlspecialchars((string) $string, \ENT_COMPAT, 'UTF-8');
    if ($string != "" && $ret == "") {
        // Invalid UTF-8. Gusss that string is iso-8859-1.
        // This will never crash as iso-8859-1 does not have invalid characher
        // sequences. (All octet's are defined.) However, it will produce
        // invalid charachers if the input encoding is not ISO-8859-1.
        // However, that is a rule violation anyway as all strings in Melt Framework
        // SHOULD be encoded in UTF-8.
        $string = \iconv("ISO-8859-1", "UTF-8", $string);
        return \htmlspecialchars($string, \ENT_COMPAT, 'UTF-8');
    }
    return $ret;
}

/** Decodes given HTML string to UTF-8. */
function decode($html) {
    return html_entity_decode($html, ENT_COMPAT, 'UTF-8');
}
