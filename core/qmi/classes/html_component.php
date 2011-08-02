<?php namespace melt\qmi;

class HtmlComponent {
    public $html_interface;
    public $html_error;
    public $id;
    public $label;
    public $type;

    public function __construct($html_interface, $label, $html_error, $id, \melt\Type $type) {
        $this->html_interface = $html_interface;
        $this->label = $label;
        $this->html_error = $html_error;
        $this->id = $id;
        $this->type = $type;
    }
}