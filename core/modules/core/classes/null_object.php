<?php namespace nmvc\core;

/**
 * Used as a placeholder for NULL when using NULL
 * could trigger dangerous fatal errors or trigger errors.
 */
class NullObject implements \ArrayAccess, \Iterator {
    public function current() {
        return $this;
    }

    public function key() {
        return null;
    }

    public function next() {
        return;
    }

    public function rewind() {
        return;
    }

    public function valid() {
        return false;
    }

    public function offsetExists($offset) {
        return false;
    }

    public function offsetGet($offset) {
        return $this;
    }

    public function offsetSet($offset, $value) {
        return;
    }

    public function offsetUnset($offset) {
        return;
    }

    public function __call($name, $arguments) {
        return $this;
    }

    public static function __callStatic($name, $arguments) {
        return $this;
    }

    public function __set($name, $value) {
        return;
    }

    public function __get($name) {
        return $this;
    }

    public function __isset($name) {
        return false;
    }

    public function __unset($name) {
        return;
    }

    public static function __set_state($array) {
        ;
    }
}