<?php namespace melt\html;

class MarkdownType extends \melt\core\TextAreaType {
    public function getInterface($name) {
        $value = escape($this->value);
        return "<textarea cols=\"32\" rows=\"3\" name=\"$name\" id=\"$name\">$value</textarea>";
    }

    public function __toString() {
        return markdown($this->value);
    }
}
