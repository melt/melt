<?php

namespace nanomvc\core;

class TextAreaType extends TextType {
    public function getInterface($name, $label) {
        $value = escape($this->value);
        return "$label <textarea name=\"$name\">$value</textarea>";
    }
}
