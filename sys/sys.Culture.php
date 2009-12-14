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
* @desc Is only used as a marker in developer mode, is replaced when packed.
* @see  sprintf()
*/
function __($str) {
    $n = func_num_args();
    if ($n > 1) {
        $data = array();
        for ($i = 1; $i < $n; $i++)
            $data[] = func_get_arg($i);
        return vsprintf($str, $data);
    } else
        return $str;
}

/*

TODO: Rewrite this...

function _try_set_language($set_to, $languages) {
    $set_to = substr($set_to, 0, 2);
    if (in_array($set_to, $languages)) {
        $_SESSION['language'] = $set_to;
        $lang_file = APP_DIR . "/lang.$set_to.php";
        require $lang_file;
        return true;
    } else
        return false;
}

function _load_language() {
    // Get array of supported languages.
    $languages = api_cache::get_cache('lang', 'lang');
    if ($languages == null) {
        $language_files = glob(APP_DIR . "/lang.??.php");
        if (count($language_files) == 0)
            die("nanoMVC Fatal Error: No language files detected. You need to install language files to enable language support.");
        $languages = array();
        foreach ($language_files as $file)
            $languages[] = substr($file, -6, 2);
        api_cache::set_cache('lang', 'lang', implode(',', $languages));
    } else
        $languages = explode(',', $languages);

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
        _try_set_language('en', $languages);
    }
    // 4. Set to one of the supported.
    _try_set_language($languages[0], $languages);
}

_load_language();*/


?>