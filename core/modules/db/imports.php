<?php
/**
 * @desc This function properly escapes and quotes any string you insert,
 * @desc making it ready to be directly inserted into your SQL queries.
 * @example Input: > a 'test' <   Output: > "a \'test\'" <
 * @return String The escaped and quoted string you inputed.
 */
function strfy($string, $max_length = -1) {
    return \nmvc\db\strfy($string, $max_length);
}

/** Convenience function for prefixing tables. */
function table($table_name) {
    return \nmvc\db\table($table_name);
}
