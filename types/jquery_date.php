<?php

class JqueryDateType extends Type {
    /**
    *@desc If initialized yet.
    */
    private $initialized = false;

    private function initialize() {
        // Write initialization stuff to the head section.
        api_application::$_application_controller->layout->enterSection("head");
        echo '<script type="text/javascript">
                $(function() {
                    $(".datepicker").datepicker({ firstDay: 1, dateFormat: "dd-mm-yy" });
                });
              </script>';
        api_application::$_application_controller->layout->exitSection();
        $this->initialized = true;
    }

    public function getSQLType() {
        return "int";
    }
    public function getSQLValue() {
        return intval($this->value);
    }
    public function getInterface($label) {
        if (!$this->initialized)
            $this->initialize();
        $date_name = $this->name;
        if ($this->value <= 1)
            $this->value = time();
        $date = date('d-m-Y', $this->value);
        $time = date('H:i', $this->value);
        return "$label <input class=\"datepicker\" type=\"text\" name=\"$date_name\" value=\"Date: $date\" />"
            . "<br /><span style=\"font-size: 9px;\">" . __('Timestamp Format')
            . ": DD MM YYYY</span>";
    }
    public function readInterface() {
        $date_name = $this->name;
        $newstamp = strval(@$_POST[$date_name]);
        // Get the numeric clusters.
        $m = preg_split('#[^0-9]+#', $newstamp);
        // Filter all empty positions.
        $m = array_values(array_filter($m, create_function('$val', 'return $val !== \'\';')));
        // Make timestamp.
        if (count($m) == 3) {
            $d = intval($m[0]);
            $mo = intval($m[1]);
            $yr = intval($m[2]);
            $time = mktime(0, 0, 0, $mo, $d, $yr);
            if ($time === false || $time === -1)
                $time = 0;
        } else
            $time = 0;
        $this->value = intval($time);
    }
    public function __toString() {
        return date('d M Y', intval($this->value));
    }

}

?>