<?php

namespace nanomvc\translate;

/**
 * Human language wrapper function for translations.
 * @see  sprintf()
 */
function __($str) {
    // Using translation!
    global $_lang_translation;
    if (config\ENABLE && TRANSLATION_AVAILIBLE) {
        if (isset($_lang_translation[$str])) {
            // Translate if translation defined.
            $translate = $_lang_translation[$str];
            if (strlen($translate) > 0)
                $str = $translate;
        } else if (config\TRANSLATION_CAPTURE) {
            // Capture.
            $count = \nanomvc\db\query("SELECT COUNT(*) FROM " . \nanomvc\db\table(config\TRANSLATION_TABLE) . " WHERE original = " . \nanomvc\db\strfy($str));
            $count = api_database::next_array($count);
            $count = intval($count[0]);
            if ($count == 0) {
                // Insert.
                \nanomvc\db\query("INSERT INTO " . \nanomvc\db\table(config\TRANSLATION_TABLE) . " (original) VALUES (" . \nanomvc\db\strfy($str) . ")");
            }
        }
    }
    $n = func_num_args();
    if ($n > 1) {
        $data = array();
        for ($i = 1; $i < $n; $i++)
            $data[] = func_get_arg($i);
        return vsprintf($str, $data);
    } else
        return $str;
}

// Import some functions to the global namespace.
include __DIR__ . "/imports.php";