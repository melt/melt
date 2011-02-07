<?php

/**
 * Works exactly like the PHP instanceof operator, however
 * it also takes a class name (string) as it's first argument.
 * @param mixed $class Class name or object to compare.
 * @param mixed $base_class The class name or object to compare with.
 * @return boolean
 * @see The PHP instanceof operator
 */
function is($class, $base_class) {
    return \nmvc\core\is($class, $base_class);
}

/**
 * This function throws an E_USER_WARNING error if the application is in
 * developer mode, and proceeds to print all arguments that where
 * passed to it.
 */
function debug() {
    call_user_func_array('\nmvc\core\debug', func_get_args());
}

/**
 * Gets the ID for a model instance or 0 for null.
 * @param \nmvc\Model $instance
 * @return integer
 */
function id($instance = null) {
    return \nmvc\core\id($instance);
}

if (!function_exists("_") || \nmvc\core\config\TRANSLATION_ENABLED) {
    /**
     * Aliast for gettext.
     */
    function _() {
        return call_user_func_array('gettext', func_get_args());
    }
}


if (!function_exists("__") || \nmvc\core\config\TRANSLATION_ENABLED) {
    /**
     * Alias for gettext.
     */
    function __() {
        return call_user_func_array('gettext', func_get_args());
    }
}

if (!function_exists("gettext") || \nmvc\core\config\TRANSLATION_ENABLED) {
    /**
     * Translates string.
     * @param string $msgid Must be string litteral expression. Function call is
     * parsed by localization engine.
     * @return string Locale translated string.
     */
    function gettext($msgid) {
        $sprintf_args = array_slice(func_get_args(), 1);
        return nmvc\core\LocalizationEngine::translate($msgid, "", "", 1, $sprintf_args);
    }
}


if (!function_exists("ngettext") || \nmvc\core\config\TRANSLATION_ENABLED) {
    /**
     * Translates by plural form.
     * @param string $msgid Must be string litteral expression. Function call is
     * parsed by localization engine.
     * @param string $msgid_plural
     * @param integer $n
     * @return string Locale translated string.
     */
    function ngettext($msgid, $msgid_plural, $n) {
        $sprintf_args = array_slice(func_get_args(), 2);
        return nmvc\core\LocalizationEngine::translate($msgid, $msgid_plural, "", $n, $sprintf_args);
    }
}

if (!function_exists("pgettext") || \nmvc\core\config\TRANSLATION_ENABLED) {
    /**
     * Translates by context.
     * @param string $context Context of string.
     * @param string $msgid Must be string litteral expression. Function call is
     * parsed by localization engine.
     * @return string Locale translated string.
     */
    function pgettext($context, $msgid) {
        $sprintf_args = array_slice(func_get_args(), 2);
        return nmvc\core\LocalizationEngine::translate($msgid, "", $context, 1, $sprintf_args);
    }
}
