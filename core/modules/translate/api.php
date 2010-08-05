<?php namespace nmvc\translate;

/**
 * This function will return the translation language the current
 * request is initialized with.
 * @return string Two letter ISO-639-1 code of the language
 * or NULL if no language is set.
 */
function get_language() {
    // Initialize translation.
    if (!defined("TRANSLATION_INITIALIZED"))
        TranslateModule::initializeTranslation();
    if (defined("LANGUAGE_SET"))
        return LANGUAGE_SET;
    else
        return null;
}

/**
 * This function will set the translation language this session should use.
 * Most likley, the request has to be reloaded for the language change to take
 * effect. If the set language does not exist, the translation will fall
 * back to the default value for this client.
 * @param string $iso_639_1 Two letter ISO-639-1 code of the language to set.
 * @return void
 */
function set_language($iso_639_1 = 'en') {
    $_SESSION['language'] = $iso_639_1;
}

/**
 * Human language wrapper function for translations.
 * @see  sprintf()
 */
function __($str) {
    // Initialize translation.
    if (!defined("TRANSLATION_INITIALIZED"))
        TranslateModule::initializeTranslation();
    // Using translation.
    global $_lang_translation;
    if (config\ENABLE) {
        if (TRANSLATION_AVAILIBLE && isset($_lang_translation[$str])) {
            // Translate if translation defined.
            $translate = $_lang_translation[$str];
            if (strlen($translate) > 0)
                $str = $translate;
        } else if (config\TRANSLATION_CAPTURE) {
            // Capture.
            $count = \nmvc\db\query("SELECT COUNT(*) FROM " . \nmvc\db\table(config\TRANSLATION_TABLE) . " WHERE original = " . \nmvc\db\strfy($str));
            $count = \nmvc\db\next_array($count);
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