<?php namespace nmvc\qmi;

class HtmlComponent {
    public $html_interface;
    public $html_error;
    public $id;
    public $label;

    public function __construct($html_interface, $label, $html_error, $id) {
        $this->html_interface = $html_interface;
        $this->label = $label;
        $this->html_error = $html_error;
        $this->id = $id;
    }
}