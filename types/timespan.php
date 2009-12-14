<?php

class TimespanType extends Type {
    public $title = "";

    private function getTokenValue($token) {
        $token_values = array(
            __("sekund") => 1,
            __("sekunder") => 1,
            __("minut") => 60,
            __("minuter") => 60,
            __("timme") => 3600,
            __("timmar") => 3600,
            __("dag") => 86400,
            __("dagar") => 86400,
            __("vecka") => 604800,
            __("veckor") => 604800,
            __("månad") => 2629743.83,
            __("månader") => 2629743.83,
            __("år") => 31557600, // Julian year
            __("år<#plural>") => 31557600);
        $token = strtolower($token);
        return (isset($token_values[$token]))? $token_values[$token]: false;
    }
    public function getSQLType() {
        return "int";
    }
    public function SQLize($data) {
        return intval($data);
    }
    public function getInterface($label, $data, $name) {
        $title = ($this->title != "")? ' title="' . $this->title . '" ': '';
        $span = ($data != 0)? $this->write($data): $date_syntax_helper;
        if (!$dateonly)
            $stamp .= $time_syntax_helper;
        return "$label <input$title type=\"text\" name=\"$name\" value=\"$span\" />"
            . "<br /><span style=\"font-size: 9px;\">" . __("Tidsintervall, exempel: 3 dagar, 5 timmar och 9 sekunder") . "</span>";
    }
    public function read($name, &$value) {
        $human_string = strval(@$_POST[$name]);
        $tokens = preg_split("#\s+#", trim($human_string));
        // Numbers + valid token = add.
        $value = 0;
        $last_number = "";
        $token_value_mode = false;
        foreach ($tokens as $token) {
            if ($token_value_mode) {
                $token_value_mode = false;
                $tv = $this->getTokenValue($token);
                if ($tv !== false) {
                    $value += floatval($last_number) * $tv;
                    $last_number = "";
                    continue;
                }
            }
            if (is_numeric($token)) {
                $last_number .= $token;
                $token_value_mode = true;
            } else
                $last_number = "";
        }
    }
    public function write($value) {
        $value = floatval($value);
        $unit_spans = array(60, 3600, 86400, 604800, 2629744, 31557600);
        $value_united = array();
        foreach (array_reverse($unit_spans) as $span) {
            $value_united[] = floor($value / $span);
            $value %= $span;
        }
        $value_united[] = $value;
        $out_formats = array(__('%d sekunder'), __('%d minuter'),  __('%d timmar'), __('%d dagar'), __('%d veckor'), __('%d månader'), __('%d år'));
        $uni_formats = array(__('en sekund'), __('en minut'), __('en timme'), __('en dag'), __('en vecka'), __('en månad'), __('ett år'));
        $glue_last = __(' och ');
        $glue = __(', ');
        $out_formats = array_reverse($out_formats);
        $uni_formats = array_reverse($uni_formats);
        $non_zero = false;
        $out_formated = array();
        foreach ($value_united as $k => $length) {
            if ($length == 1) {
                $out_formated[] = $uni_formats[$k];
                $non_zero = true;
            } else if ($length == 0) {
                if (!$non_zero)
                    continue;
                $out_formated[] = sprintf($out_formats[$k], 0);
            } else {
                $out_formated[] = sprintf($out_formats[$k], $length);
                $non_zero = true;
            }
        }
        $out_formated = array_reverse($out_formated);
        if (count($out_formated) == 0)
            return __("Ingen tid");
        else if (count($out_formated) > 1)
            return implode($glue, array_reverse(array_slice($out_formated, 1))) . $glue_last . $out_formated[0];
        else
            return $out_formated[0];
    }

}

?>