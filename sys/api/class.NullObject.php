<?php

/**
 * This is a placeholder for null references in Views.
 * It prevents exceptions from beeing thrown when accessing unset variables.
 */
class NullObject {
    // Singleton.
    private function  __construct() { }
    function __get($name) {
        return $this;
    }
    function __set($name, $value) {
        throw new Exception("Setting variable on NullObject!");
    }
    function __isset($name) {
        return false;
    }
    function __unset($name) {
        return;
    }
    function __toString() {
        return null;
    }
    public function __call($name,  $arguments) {
        return $this;
    }
    public static function getInstance() {
        static $instance = null;
        if ($instance == null)
            $instance = new NullObject();
        return new NullObject();
    }
}
?>