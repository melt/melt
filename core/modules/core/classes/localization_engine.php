<?php namespace nmvc\core;

class LocalizationEngine {
    /**
     * Translation lookup array.
     * "aa" = Locale lowercase ISO 639-1 code
     * "msgid" = Original string.
     *
     * /translation entry/ = array(
     *      "fuzzy => boolean,
     *      "translations" => array(
     *          0 => /translation 0/
     *          1 => /translation 1/
     *          ..
     *          N => /translation N/
     *      )
     * )
     *
     * "aa" => array(
     *      "plural_forms" => array(
     *          "nplurals" => 2
     *          "indexformula" => "n == 1? 0: 1;"
     *      )
     *      "last_translator" => string
     *      "last_import" => integer
     *      "strings" => array(
     *          "msgid" => array(
     *              "" = /translation entry/
     *              "context 1" => /translation entry/
     *              "context 2" => /translation entry/
     *              ..
     *              "context n" => /translation entry/
     *          )
     *      )
     *  )
     *
     */
    public $locale_data = array();
    private $current_locale = null;

    private function getNewLanguage() {
        return array(
            "plural_forms" => array(
                "nplurals" => 2,
                "indexformula" => null,
            ),
            "last_translator" => "",
            "last_import" => \time(),
            "strings" => array(),
        );
    }
    
    /**
     * Removes locale if it doesn't exist.
     * @param string $locale
     */
    public function removeLocale($locale) {
        unset($this->locale_data[$locale]);
        $this->save();
    }

    /**
     * Creates a new locale if it doesn't exist.
     * @param string $locale Two letter ISO 639-1 locale identifier.
     */
    public function createLocale($locale) {
        $locale = \strtolower($locale);
        if (\array_key_exists($locale, $this->locale_data))
            return;
        if (!\preg_match('#^[a-z][a-z]$#', $locale))
            \trigger_error("Invalid locale identifier. Has to be two letter language code.", \E_USER_ERROR);
        $this->locale_data[$locale] = $this->getNewLanguage();
        $this->save();
    }

    /**
     * Parses a file for translate invokes.
     * @param string $file_path File path to parse.
     * @return array An array of arrays of the form
     * (msgid, pluralid, context, reference).
     */
    private function parseTranslateInvokes($file_path) {
        $tokens = \token_get_all(\file_get_contents(APP_DIR . "/" . $file_path));
        // Declare iteration variables.
        $translate_invokes = array();
        $line = 0;
        $token = null;
        $token_type = null;
        $ignore_next_string = false;
        $function_started = false;
        $pending_arguments = 0;
        $arguments = array();
        $pending_function = null;
        foreach ($tokens as $token_data) {
            $expected = false;
            if (!is_array($token_data))
                $token = $token_type = $token_data;
            else
                list($token_type, $token, $line) = $token_data;
            switch ($token_type) {
            case "(":
                if ($function_started || $pending_arguments == 0)
                    break;
                $function_started = true;
                $expected = true;
                break;
            case ",":
            case \T_WHITESPACE:
            case \T_COMMENT:
            case \T_DOC_COMMENT:
                $expected = true;
                break;
            case \T_FUNCTION:
            case \T_CLASS:
                $ignore_next_string = true;
                break;
            case \T_CONSTANT_ENCAPSED_STRING:
                if (!$function_started)
                    break;
                $arguments[] = eval("return $token;");
                $pending_arguments--;
                $reference = "$file_path:$line";
                if ($pending_arguments == 0) {
                    switch ($pending_function) {
                    case "gettext":
                        $translate_invokes[] = array($arguments[0], "", "", $reference);
                        break;
                    case "ngettext":
                        $translate_invokes[] = array($arguments[0], $arguments[1], "", $reference);
                        break;
                    case "pgettext":
                        $translate_invokes[] = array($arguments[1], "", $arguments[0], $reference);
                        break;
                    }
                    $function_started = false;
                    $arguments = array();
                }
                $expected = true;
                break;
            case \T_STRING:
                if ($ignore_next_string) {
                    $ignore_next_string = false;
                    break;
                } else if ($pending_arguments > 0)
                    break;
                if ($token[0] == "\\")
                    $token = \substr($token, 1);
                if ($token == "__" || $token == "_" || $token == "gettext") {
                    $pending_arguments = 1;
                    $pending_function = "gettext";
                    $expected = true;
                } else if ($token == "ngettext") {
                    $pending_arguments = 2;
                    $pending_function = $token;
                    $expected = true;
                } else if ($token == "pgettext") {
                    $pending_arguments = 2;
                    $pending_function = $token;
                    $expected = true;
                }
                break;
            }
            if (!$expected && $pending_arguments > 0)
                trigger_error("Error when when updating translation at $file_path:$line.\n"
                . "Expected literal string expression (T_CONSTANT_ENCAPSED_STRING) in translation invoke. Found: " . (is_integer($token_type)? \token_name($token_type): $token), \E_USER_ERROR);
        }
        return $translate_invokes;
    }

    private static function strToCStr($str) {
        return \str_replace(array('\\', '"', "\t", "\n", "\r", "\0"), array('\\\\', '\"', '\t', '\n', '\r', '\0'), $str);
    }

    private static function cstrToStr($cstr) {
        return \str_replace(array('\\\\', '\"', '\t', '\n', '\r', '\0'), array('\\', '"', "\t", "\n", "\r", "\0"), $cstr);
    }

    private static function getPoBlock($msgctxt, $msgid, $msgid_plural = "", $translations = array(), $fuzzy = false, $references = array(), $no_meta = false, $comments = "") {
        $po_block = "";
        if ($comments != "")
            $po_block .= "#" . \str_replace("\n", "\n# ", $comments) . "\n";
        if (!$no_meta) {
            if (\count($references) > 0)
                $po_block .= "#: " . \implode(" ", $references) . "\n";
            $po_block .= "#, php-format" . ($fuzzy? ", fuzzy": "") . "\n";
        }
        if ($msgctxt != "")
            $po_block .= 'msgctxt  "' . self::strToCStr($msgctxt) . "\"\n";
        $po_block .= 'msgid "' . self::strToCStr($msgid) . "\"\n";
        if ($msgid_plural != "")
            $po_block .= 'msgid_plural "' . self::strToCStr($msgid_plural) . "\"\n";
        $translations = \array_values($translations);
        if (count($translations) < 2) {
            $msgstr = count($translations) > 0? $translations[0]: "";
            $po_block .= 'msgstr "' . self::strToCStr($msgstr) . "\"\n";
        } else {
            foreach ($translations as $index => $translation)
                $po_block .= 'msgstr[' . $index . '] "' . self::strToCStr($translation) . "\"\n";
        }
        $po_block .= "\n";
        return $po_block;
    }
    
    /**
     * Exports a locale to a PO file and scans for translation changes in
     * the source code in the process.
     * @param string $locale
     * @param integer $levdist_threshold Levenshtein distance from new
     * strings to old strings for them to count as similar but fuzzy.
     * @return string
     */
    public function exportLanguage($locale, $min_similar_string_dist = 0.85) {
        \set_time_limit(0);
        if (!\array_key_exists($locale, $this->locale_data))
            trigger_error("Locale $locale does not exist!", \E_USER_ERROR);
        $old_locale_strings = $this->locale_data[$locale]["strings"];
        // Scan trough all source code and update locale strings.
        $new_locale_strings = array();
        $plural_ids = array();
        $references = array();
        $php_files = grep(APP_DIR, '#.*\.php$#');
        $untranslated_strings = array();
        $used_old_locale_strings = array();
        foreach ($php_files as $php_file) {
            if ($php_file == "localization.php")
                continue;
            if (\nmvc\string\starts_with($php_file, "vendors/"))
                continue;
            if (\preg_match('#^modules/([^/]+)/#', $php_file, $matches)) {
                $module = $matches[1];
                if (!module_loaded($module))
                    continue;
            }
            foreach ($this->parseTranslateInvokes($php_file) as $translate_invoke) {
                list($msgid, $plural_id, $context, $reference) = $translate_invoke;
                if (isset($new_locale_strings[$msgid][$context])) {
                    // Translation exists in multiple locations.
                } else if (isset($old_locale_strings[$msgid][$context])) {
                    // Translation already exists. Transfer old locale string to new.
                    $new_locale_strings[$msgid][$context] = $old_locale_strings[$msgid][$context];
                    // Mark this old translation as used.
                    $used_old_locale_strings[$msgid][$context] = true;
                } else {
                    // Translation is new. Find old, similar translation.
                    $best_match = null;
                    if (\ceil($min_similar_string_dist * \strlen($msgid)) < \strlen($msgid)) {
                        $record = $min_similar_string_dist;
                        foreach ($old_locale_strings as $old_msgid => $old_contexts) {
                            if (\count($old_contexts) == 0)
                                continue;
                            $similarity = \nmvc\string\lcs_similarity($msgid, $old_msgid, $record);
                            if ($similarity !== false && $similarity > $record) {
                                $best_match = $old_contexts;
                                $record = $similarity;
                            }
                        }
                    }
                    if ($best_match !== null) {
                        // Use same context or just pick one.
                        if (\array_key_exists($context, $best_match))
                            $fuzzy_translation = $best_match[$context];
                        else
                            $fuzzy_translation = \reset($best_match);
                        $new_locale_strings[$msgid][$context] = $fuzzy_translation;
                        $new_locale_strings[$msgid][$context]["fuzzy"] = true;
                        // Mark this old translation as used.
                        $used_old_locale_strings[$msgid][$context] = true;
                    } else {
                        // Translation is new and couldn't be matched, just add.
                        $new_locale_strings[$msgid][$context] = array(
                            "fuzzy" => true,
                            "translations" => array(),
                        );
                    }
                }
                if (!isset($plural_ids[$msgid][$context]) || $plural_id != "")
                    $plural_ids[$msgid][$context] = $plural_id;
                $references[$msgid][$context][] = $reference;
            }
        }
        // Add all translated deprecated strings.
        foreach ($old_locale_strings as $old_msgid => $old_contexts) {
            foreach ($old_contexts as $old_context => $tranlation_entry) {
                if (isset($used_old_locale_strings[$old_msgid][$old_context]))
                    continue;
                if (count($tranlation_entry["translations"]) == 0)
                    continue;
                $new_locale_strings[$old_msgid][$old_context] = $old_locale_strings[$old_msgid][$old_context];
                $plural_ids[$old_msgid][$old_context] = "";
                $references[$old_msgid][$old_context] = array();
            }
        }
        $comment = "NanoMVC Application Automatically Generated .PO Translation File";
        $headers = array(
            "Project-Id-Version" => "",
            "Report-Msgid-Bugs-To" => "http://getsatisfaction.com/omnicloud/products/omnicloud_nanomvc",
            "POT-Creation-Date" => date("r"),
            "PO-Revision-Date" => date("r", $this->locale_data[$locale]["last_import"]),
            "Last-Translator" => $this->locale_data[$locale]["last_translator"],
            "Language-Team" => "",
            "Language" => $locale,
            "Content-Type" => "text/plain; charset=UTF-8",
            "Content-Transfer-Encoding" => "8bit",
        );
        if ($this->locale_data[$locale]["plural_forms"]["indexformula"] != null) {
            $nplurals = $this->locale_data[$locale]["plural_forms"]["nplurals"];
            $indexformula = $this->locale_data[$locale]["plural_forms"]["indexformula"];
            $headers["Plural-Forms"] = "nplurals=$nplurals; plural=$indexformula";
        }
        $header = "";
        foreach ($headers as $key => $value)
            $header .= "$key: $value\n";
        $po_file_content = self::getPoBlock("", "", "", array($header), false, array(), true, $comment);
        foreach ($new_locale_strings as $msgid => $new_locale_contexts) {
            foreach ($new_locale_contexts as $context => $translation) {
                $msgid_plural = $plural_ids[$msgid][$context];
                $translations = $translation["translations"];
                $fuzzy = $translation["fuzzy"] == true;
                $entry_references = $references[$msgid][$context];
                $po_file_content .= self::getPoBlock($context, $msgid, $msgid_plural, $translations, $fuzzy, $entry_references);
            }
        }
        return $po_file_content;
    }

    public function importLanguage($po_file_content, $locale_code) {
        $po_file_sections = \preg_split('#\n\s*\n#', $po_file_content);
        $po_data = array();
        foreach ($po_file_sections as $po_file_section) {
            $po_file_rows = \preg_split('#\s*\n\s*#', \trim($po_file_section));
            $key = "";
            $value = "";
            $po_section_data = array();
            $add_po_section_data = function() use (&$key, &$value, &$po_section_data) {
                if ($key == "")
                    return;
                $po_section_data[$key] = $value;
                $value = "";
                $key = "";
            };
            foreach ($po_file_rows as $po_file_row) {
                if ($po_file_row == "")
                    continue;
                if (\nmvc\string\starts_with($po_file_row, "#,")) {
                    if (false !== \strpos($po_file_row, "fuzzy"))
                        $po_section_data["fuzzy"] = true;
                    continue;
                }
                if ($po_file_row[0] == "#")
                    continue;
                if ($po_file_row[0] == '"') {
                    $value .= self::cstrToStr(\substr($po_file_row, 1, -1));
                    continue;
                }
                if (\preg_match('#^([^\s]+)\s+(.*)$#', $po_file_row, $matches)) {
                    $add_po_section_data();
                    $key = $matches[1];
                    $value = self::cstrToStr(\substr($matches[2], 1, -1));
                } else
                    trigger_error("Syntax error on row: " . $po_file_row . "\n(Row ignored.)", \E_USER_WARNING);
            }
            $add_po_section_data();
            if (!isset($po_section_data["msgid"]))
                continue;
            $msgid = $po_section_data["msgid"];
            $context = isset($po_section_data["msgctxt"])? $po_section_data["msgctxt"]: "";
            if (isset($po_data[$msgid][$context]))
                trigger_error("Found msgid/context specified twice. ('$msgid', '$context') Overwriting...", \E_USER_WARNING);
            $po_data[$msgid][$context] = $po_section_data;
        }
        if (!isset($po_data[""][""]))
            trigger_error("PO file does not include headers!", \E_USER_ERROR);
        $headers = $po_data[""][""]["msgstr"];
        unset($po_data[""][""]);
        $charset = null;
        $last_translator = null;
        $nplurals = null;
        $indexformula = null;
        foreach (\explode("\n", $headers) as $header) {
            $header = \trim($header);
            if ($header == "")
                continue;
            if (!\preg_match('#^([^:]+):\s*(.*)$#', $header, $matches))
                continue;
            $key = strtolower($matches[1]);
            $value = $matches[2];
            switch ($key) {
            case "language":
                if ($locale_code == null)
                    $locale_code = strtolower($value);
                break;
            case "content-type":
                if (!\preg_match('#charset=([^\s;]+)#', $value, $matches))
                    continue;
                $charset = \strtoupper($matches[1]);
                break;
            case "last-translator":
                $last_translator = $value;
                break;
            case "plural-forms":
                if (\preg_match('#nplurals=([\d]+)#', $value, $matches))
                    $nplurals = \intval($matches[1]);
                if (\preg_match('#plural=([\^;]+)#', $value, $matches))
                    $indexformula = $matches[1];
                break;
            }
        }
        if ($charset !== null && $charset !== "UTF-8")
            trigger_error("PO file specified charset '$charset', NanoMVC currently only supports UTF-8 encoded PO files. Change encoding in your PO editor!", \E_USER_ERROR);
        if ($locale_code == null || \strlen($locale_code) != 2)
            trigger_error("Input PO file did not specify a language or language has invalid format! Expected two letter ISO 639-1 identifier.", \E_USER_ERROR);
        if (!\array_key_exists($locale_code, $this->locale_data))
            $locale = $this->getNewLanguage();
        else
            $locale = $this->locale_data[$locale_code];
        if ($last_translator !== null)
            $locale["last_translator"] = $last_translator;
        $locale["last_import"] = time();
        if ($nplurals !== null)
            $locale["plural_forms"]["nplurals"] = $nplurals;
        if ($indexformula !== null)
            $locale["plural_forms"]["indexformula"] = $indexformula;
        $new_strings = array();
        foreach ($po_data as $msgid => $contexts) {
            foreach ($contexts as $context => $translation_entry) {
                $translation_data = array();
                $translation_data["fuzzy"] = \array_key_exists("fuzzy", $translation_entry);
                $translations = array();
                if (\array_key_exists("msgstr", $translation_entry)) {
                    $msgstr = $translation_entry["msgstr"];
                    if ($msgstr != "")
                        $translations[0] = $msgstr;
                } else {
                    foreach ($translation_entry as $key => $value) {
                        if (!\preg_match('#^msgstr\[(\d+)\]$#', $key, $matches))
                            continue;
                        $index = \intval($matches[1]);
                        if ($value != "")
                            $translations[$index] = $value;
                    }
                }
                // Skip empty translations.
                if (count($translations) == 0)
                    continue;
                $translation_data["translations"] = $translations;
                $new_strings[$msgid][$context] = $translation_data;
            }
        }
        $locale["strings"] = $new_strings;
        $this->locale_data[$locale_code] = $locale;
        $this->save();
    }

    public function getLocales() {
        return \array_keys($this->locale_data);
    }

    /**
     * Makes a gnu gettext type string translation.
     * @param string $str String to translate.
     * @param string $plural_str Plural form of string.
     * @param integer $n
     * @param context $context
     * @return string Translation of string.
     */
    public static function translate($str, $plural_str = "", $context = "", $n = 1, $sprintf_args = array()) {
        // Cast arguments to their correct data types.
        $str = \strval($str);
        $plural_str = \strval($plural_str);
        $context = \strval($context);
        $n = intval($n);
        if ($n < 0)
            $n = 0;
        if (config\TRANSLATION_ENABLED) {
            // Load current translation from locale.
            static $translate_strings = null;
            static $maxindex = null;
            static $indexformula = null;
            if ($translate_strings === null) {
                $locale_engine = self::get();
                if (\array_key_exists($locale_engine->current_locale, $locale_engine->locale_data)) {
                    $language_data = $locale_engine->locale_data[$locale_engine->current_locale];
                    $translate_strings = $language_data["strings"];
                    $maxindex = intval($language_data["plural_forms"]["nplurals"]) - 1;
                    $indexformula = $language_data["plural_forms"]["indexformula"];
                } else {
                    $translate_strings = array();
                }
            }
            // Look up translation with specified context.
            if (\array_key_exists($str, $translate_strings)) {
                $translations = $translate_strings[$str];
                if (\array_key_exists($context, $translations)) {
                    $translations = $translations[$context]["translations"];
                    if (count($translations) > 0) {
                        // Calculate translation index.
                        $index = ($indexformula != null)? intval(eval($indexformula)): ($n == 1? 0: 1);
                        if ($index > $maxindex)
                            $index = $maxindex;
                        if ($index < 0)
                            $index = 0;
                        if (\array_key_exists($index, $translations))
                            $translation = $translations[$index];
                        else
                            $translation = \reset($translations);
                        return @\vsprintf($translation, $sprintf_args);
                    }
                }
            }
        }
        // No translation used. Just do standard plural switch.
        if ($plural_str == "")
            return @\vsprintf($str, $sprintf_args);
        if ($n > 1)
            return @\vsprintf($plural_str, $sprintf_args);
        else
            return @\vsprintf($str, $sprintf_args);
    }

    public static function __set_state($saved_state) {
        return new LocalizationEngine($saved_state['locale_data']);
    }

    private function trySetLanguage($locale) {
        $locale = \strtolower(\substr($locale, 0, 2));
        if (\strlen($locale) != 2)
            return false;
        if (\array_key_exists($locale, $this->locale_data)) {
            $_SESSION['core\locale'] = $locale;
            $this->current_locale = $locale;
            // Since not using default language, we can append a content-language header.
            if (!\headers_sent())
                \header("Content-Language: " . $locale);
            return true;
        } else
            return false;
    }

    private function __construct($locale_data) {
        $this->locale_data = $locale_data;
        // Determine what language to use if supporting more than one language.
        if (\count($locale_data) > 1) {
            $locale = null;
            if (isset($_SESSION['core\locale']))
                // 1. Set by session, if it can.
                if ($this->trySetLanguage($_SESSION['core\locale']))
                    return;
                unset($_SESSION['core\locale\language']);
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                // 2. Set by accept-language, if it can.
                $server_accept_language = \preg_replace('#\s#', '', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
                $accept_lang_tokens = \array_reverse(\explode(',', $server_accept_language));
                $lang_try_order = array();
                $q = 0.5;
                foreach ($accept_lang_tokens as $lang_token) {
                    $lang_token = \trim($lang_token);
                    if (false !== ($qp = \strpos($lang_token, ';q=')))
                        $q = \floatval(\substr($lang_token, $qp + 3));
                    if ($q < config\TRANSLATION_MIN_Q)
                        continue;
                    $lang_token = \substr($lang_token, 0, 2);
                    $lang_try_order[$lang_token] = $q;
                }
                \arsort($lang_try_order);
                foreach ($lang_try_order as $locale => $q)
                    if ($this->trySetLanguage($locale))
                        return;
            }
            // 3. Set to default language, if it can.
            if ($this->trySetLanguage(config\DEFAULT_LANGUAGE))
                return;
        }
        // 4. Set to the first of the supported.
        $supported_locales = \array_keys($locale_data);
        $this->trySetLanguage(\reset($supported_locales));
    }

    /**
     * Returns the currently set locale or NULL if no locale set.
     */
    public function getLocale() {
        return $this->current_locale;
    }

    /**
     * Sets the locale for the next request.
     * @param string $locale Two letter ISO 639-1 locale identifier.
     */
    public static function setNextLocale($locale) {
         $_SESSION['core\locale'] = \substr($locale, 0, 2);
    }

    private function save() {
        if (!\APP_IN_DEVELOPER_MODE)
            return;
        $localization_path = APP_DIR . "/localization.php";
        $data = '<?php return ' . \var_export($this, true) . ';';
        \file_put_contents($localization_path, $data);
    }

    public static function get() {
        static $instance = null;
        if ($instance !== null)
            return $instance;
        $localization_path = APP_DIR . "/localization.php";
        if (\file_exists($localization_path)) {
            $instance = require($localization_path);
            if (!($instance instanceof LocalizationEngine))
                \trigger_error("Localization data is corrupt. Did not return expected localization engine.", \E_USER_ERROR);
        } else {
            $instance = new LocalizationEngine(array());
            $instance->save();
        }
        return $instance;
    }



}