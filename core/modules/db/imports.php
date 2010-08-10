<?php

/**
 * This function properly escapes and quotes any string you insert,
 * making it ready to be directly inserted into your SQL queries.
 * @example Input: a 'test' Output: "a \'test\'"
 * @param string $string
 * @param integer $max_length Maximum length of string in charachers.
 * Only use this parameter if string contains UTF-8 text and not binary data.
 * @return string The escaped and quoted string you inputed.
 */
function strfy($string, $max_length = null) {
    return \nmvc\db\strfy($string, $max_length);
}

/**
 * Convenience function for prefixing tables.
 * @param string $table_name
 * @return string
 */
function table($table_name) {
    return \nmvc\db\table($table_name);
}
