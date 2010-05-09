<?php

namespace nmvc\core;

class TextAreaType extends TextType {
    public function getInterface($name) {
        $value = escape($this->value);
        return "<textarea name=\"$name\">$value</textarea>";
    }
}
