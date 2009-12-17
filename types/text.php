<?php

class TextType extends Type {
    public function getSQLType() {
        return "text";
    }
    public function getSQLValue() {
        return api_database::strfy($this->value);
    }
    public function getInterface($label) {
        $name = $this->name;
        $value = api_html::escape($this->value);
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }
    public function readInterface() {
        $this->value = @$_POST[$this->name];
    }
    public function __toString() {
        return api_html::escape(strval($this->value));
    }
}

?>
