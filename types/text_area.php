<?php

class TextAreaType extends Type {
    public function getSQLType() {
        return "text";
    }
    public function SQLize($data) {
        return api_database::strfy($data);
    }
    public function getInterface($label, $data, $name) {
        $data = api_html::escape($data);
        return "$label <textarea name=\"$name\">$data</textarea>";
    }
    public function read($name, &$value) {
        $value = @$_POST[$name];
    }
    public function write($value) {
        return api_html::escape(strval($value));
    }
}

?>
