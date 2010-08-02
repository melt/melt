<?php namespace nmvc;

/**
 * A nanoMVC module provides some sort of API and
 * can also provide request processing in some way.
 */
abstract class Module {
    /**
     * Overridable event-function.
     * Called just before anything is used in this module, except the API.
     */
    public static function beforeLoad() {
        return;
    }

    /**
     * Overridable event-function.
     * Called just before the request is processed and evaluated
     * for further routing.
     */
    public static function beforeRequestProcess() {
        return;
    }

    /**
     * Allows catching requests that would otherwise have 404'd.
     * @param array $url_tokens Url tokens.
     */
    public static function catchRequest($url_tokens) {
        return;
    }

    
    /**
     * Returns the author of this module.
     * One line of plain text. No special syntax required.
     */
    public abstract static function getAuthor();

    /**
     * Returns version of this module.
     * One line of plain text. No special syntax required.
     */
    public abstract static function getVersion();

    /**
     * Returns module information, including licensing, etc.
     * No special syntax required. Basic inline HTML is recommended in output.
     */
    public abstract static function getInfo();
}

/**
 * A module bundled with the nanoMVC core.
 */
abstract class CoreModule extends Module {
    public static function getAuthor() {
        $year = date("Y");
        return "Hannes Landeholm, Omnicloud AB, ©$year";
    }

    public static function getInfo() {
        return "<b>Internal core module, bundled with nanoMVC</b>"
        . "For licensing information, refer to your nanoMVC core licence.";
    }

    public static function getVersion() {
        return nmvc\VERSION;
    }
}

