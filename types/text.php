<?php

class TextType extends Type {
    public function getSQLType() {
        return "text";
    }
    public function SQLize($data) {
        return api_database::strfy($data);
    }
    public function getInterface($label, $data, $name) {
        $data = api_html::escape($data);
        return "$label <input type=\"text\" name=\"$name\" value=\"$data\" />";
    }
    public function read($name, &$value) {
        $value = @$_POST[$name];
    }
    public function write($value) {
        return api_html::escape(strval($value));
    }
}

?>
