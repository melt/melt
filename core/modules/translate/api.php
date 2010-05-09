<?php namespace nmvc\translate;

/**
 * Human language wrapper function for translations.
 * @see  sprintf()
 */
function __($str) {
    // Initialize
    static $initialized = false;
    if (!$initialized) {
        TranslateModule::initializeTranslation();
        $initialized = true;
    }
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
            $count = \nmvc\db\query("SELECT COUNT(*) FROM " . \nmvc\db\table(config\TRANSLATION_TABLE) . " WHERE original = " . \nmvc\db\strfy($str));
            $count = api_database::next_array($count);
            $count = intval($count[0]);
            if ($count == 0) {
                // Insert.
                \nmvc\db\query("INSERT INTO " . \nmvc\db\table(config\TRANSLATION_TABLE) . " (original) VALUES (" . \nmvc\db\strfy($str) . ")");
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