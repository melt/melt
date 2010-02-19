<?php


// Explicitly set the default timezone if neccessary.
if (function_exists("date_default_timezone_set"))
    date_default_timezone_set(@date_default_timezone_get());

// Using UTF-8 for everything.
iconv_set_encoding("internal_encoding", "UTF-8");
iconv_set_encoding("output_encoding", "UTF-8");


/**
* @desc Human language wrapper function for translations.
*       If given additional parameters, theese works the same way sprintf does.
* @see  sprintf()
*/
function __($str) {
    // Using translation!
    global $_lang_translation;
    if (CONFIG::$translation) {
        if (isset($_lang_translation[$str])) {
            // Translate if translation defined.
            $translate = $_lang_translation[$str];
            if (strlen($translate) > 0)
                $str = $translate;
        } else if (CONFIG::$translation_capture) {
            // Capture.
            $count = api_database::query("SELECT COUNT(*) FROM " . Config::$sql_database . "."
            . Config::$translation_table . " WHERE original = " . api_database::strfy($str));
            $count = api_database::next_array($count);
            $count = intval($count[0]);
            if ($count == 0) {
                // Insert.
                api_database::query("INSERT INTO " . Config::$sql_database . "."
                . Config::$translation_table . " (original) VALUES (" . api_database::strfy($str) . ")");
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

// Exports a fresh translation from the database.
function _export_translation($language) {
    // See if the language exists.
    api_database::enable_display();
    $language = strtolower($language);
    $lang_table = api_database::query("DESCRIBE " . Config::$translation_table);
    while (false !== ($column = api_database::next_array($lang_table))) {
        $column_name = strtolower($column[0]);
        if ($column_name == $language) {
            $out_buffer = "<?php\nglobal \$_lang_translation;\n\$_lang_translation = array(\n";
            $translations = api_database::query("SELECT original,$column_name FROM "
            . Config::$sql_database . "." . Config::$translation_table);
            while (false !== ($row = api_database::next_array($translations))) {
                $original = var_export($row[0], true);
                $translate = var_export($row[1], true);
                $out_buffer .= "\t$original =>\n\t\t$translate,\n\n";
            }
            $out_buffer .= ");?>";
            $filename = APP_DIR . "/lang.$language.php";
            file_put_contents($filename, $out_buffer);
            die("\n\nTranslation export successful!\nTranslation was written to: $filename");
        }
    }
    die("The language you specified: $language, does not exist in the language table. Export failed!");
}

// Ignore the rest of this file if not translating.
if (!CONFIG::$translation)
    return;

function _try_set_language($set_to, $languages) {
    $set_to = substr($set_to, 0, 2);
    if (in_array($set_to, $languages)) {
        $_SESSION['language'] = $set_to;
        define("LANGUAGE_SET", $set_to);
        define("LANGUAGE_FILE", APP_DIR . "/lang.$set_to.php");
        require LANGUAGE_FILE;
        // Since not using default language, we can append a content-language header.
        header("Content-Language: " . LANGUAGE_SET);
        return true;
    } else
        return false;
}

function _load_language() {
    // Get array of supported languages.
    $language_files = glob(APP_DIR . "/lang.??.php");
    $languages = array();
    foreach ($language_files as $file)
        $languages[] = substr($file, -6, 2);

    if (count($languages) == 0) {
        // No translation availible, disable it.
        CONFIG::$translation = false;
        return;
    }

    // Determine what language to use if supporting more than one language.
    if (count($languages) > 1) {
        $lang = null;
        if (isset($_SESSION['language']))
            // 1. Set by session.
            if (_try_set_language($_SESSION['language'], $languages))
                return;
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // 2. Set by accept-language.
            $server_accept_language = preg_replace('#\s#', '', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $accept_langs = explode(',', $server_accept_language);
            $lang_try_order = array();
            foreach ($accept_langs as $lang) {
                if (false !== ($qp = strpos($lang, ';q='))) {
                    $q = floatval(substr($lang, $qp + 3));
                } else
                    $q = 1;
                $lang = substr($lang, 0, 2);
                $lang_try_order[$lang] = $q;
            }
            arsort($lang_try_order);
            foreach ($lang_try_order as $lang => $q)
                if (_try_set_language($lang, $languages))
                    return;
        }
        // 3. Set to english.
        if (_try_set_language('en', $languages))
            return;
    }
    // 4. Set to one of the supported.
    _try_set_language($languages[0], $languages);
}

_load_language();

?>