<?php namespace melt\js;

class AutoresizeTextAreaType extends \melt\core\TextAreaType {
    public $animate = true;
    public $animateDuration = 150;
    public $extraSpace = 20;
    public $limit = 1000;

    public function getInterface($name) {
        $value = escape($this->value);
        $options = \json_encode(array(
            "animate" => $this->animate,
            "animateDuration" => $this->animateDuration,
            "extraSpace" => $this->extraSpace,
            "limit" => $this->limit,
        ));
        return "<textarea name=\"$name\" id=\"$name\">$value</textarea>
        <script type=\"text/javascript\">
            $(document).ready(function() {
                $('#$name').autoResize(
                    $options
                );
            });
        </script>";
    }

    public function __toString() {
        return escape($this->value);
    }
}
