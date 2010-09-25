<?php namespace nmvc\core;

class TextAreaType extends TextType {
    public function getInterface($name) {
        $value = escape($this->value);
        return "<textarea cols=\"32\" rows=\"3\" name=\"$name\" id=\"$name\">$value</textarea>";
    }

    public function __toString() {
        $value = escape($this->value);
        return "<pre>$value</pre>";
    }
}
