<?php namespace nmvc\html;

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
    $string = (string) $string;
    $ret = htmlspecialchars($string, ENT_COMPAT, 'UTF-8');
    if ($string != "" && $ret == "") {
        // Invalid UTF-8. Gusss that string is iso-8859-1.
        // This will never crash as iso-8859-1 does not have invalid characher
        // sequences. (All octet's are defined.) However, it will produce
        // invalid charachers if the input encoding is not ISO-8859-1.
        // However, that is a rule violation anyway as all strings in nanoMVC
        // SHOULD be encoded in UTF-8.
        $string = iconv("ISO-8859-1", "UTF-8", $string);
        return htmlspecialchars($string, ENT_COMPAT, 'UTF-8');
    }
    return $ret;
}

/** Decodes given HTML string to UTF-8. */
function decode($html) {
    return html_entity_decode($html, ENT_COMPAT, 'UTF-8');
}

/**
 * Outputs a linked tree from the given iterator.
 * The models on it's nodes is expected to have a getUrl function.
 * @param nmvc\core\ModelTree $iterator The tree iterator.
 * @param string $id ID of tree (if specified).
 * @param string $get_url_arg If specified, the first argument to getUrl().
 */
function create_linked_tree(\nmvc\core\ModelTree $tree, $get_url_arg = null) {
    if (count($tree->getBranch()) == 0)
        return;
    echo "<ul>";
    foreach ($tree->getBranch() as $node) {
        $node_obj = $node->getNode();
        $cls_name = \nmvc\string\cased_to_underline(basename(str_replace('\\', '/', get_class($node_obj))));
        $cls_name = substr($cls_name, 0, -6);
        echo "<li class=\"" . $cls_name . "\">";
        $url = $node_obj->getUrl($get_url_arg);
        echo "<a href=\"$url\">$node_obj</a>";
        if (count($node->getBranch()) > 0)
            create_linked_tree($node, null, $get_url_arg);
        echo "</li>";
    }
    echo "</ul>";
}
