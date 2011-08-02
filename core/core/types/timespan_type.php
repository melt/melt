<?php namespace melt\core;

class TimespanType extends \melt\AppType {
    public $title = "";

    private function getTokenValue($token) {
        $token_values = array(
            __("second") => 1,
            __("seconds") => 1,
            __("minute") => 60,
            __("minutes") => 60,
            __("hour") => 3600,
            __("hours") => 3600,
            __("day") => 86400,
            __("days") => 86400,
            __("week") => 604800,
            __("weeks") => 604800,
            __("month") => 2629743.83,
            __("months") => 2629743.83,
            __("year") => 31557600, // Julian year
            __("years") => 31557600);
        $token = strtolower($token);
        return (isset($token_values[$token]))? $token_values[$token]: false;
    }
    public function getSQLType() {
        return "bigint";
    }
    public function getSQLValue() {
        return intval($this->value);
    }
    public function getInterface($name) {
        $title = ($this->title != "")? ' title="' . $this->title . '" ': '';
        $span = ($this->value != 0)? (string) $this: $date_syntax_helper;
        if (!$dateonly)
            $stamp .= $time_syntax_helper;
        return "<input$title type=\"text\" name=\"$name\" id=\"$name\" value=\"$span\" />"
            . "<br /><span style=\"font-size: 9px;\">" . __("Time interval, example: 9 days, 5 hours and 3 minutes.") . "</span>";
    }
    public function readInterface($name) {
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
        $this->value = $value;
    }
    public function __toString() {
        $value = floatval($this->value);
        $unit_spans = array(60, 3600, 86400, 604800, 2629744, 31557600);
        $value_united = array();
        foreach (array_reverse($unit_spans) as $span) {
            $value_united[] = floor($value / $span);
            $value %= $span;
        }
        $value_united[] = $value;
        $out_formats = array(__('%d seconds'), __('%d minutes'),  __('%d hours'), __('%d days'), __('%d weeks'), __('%d months'), __('%d years'));
        $uni_formats = array(__('one second'), __('one minute'), __('one hour'), __('one day'), __('one week'), __('one month'), __('one year'));
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
            return __("No Time");
        else if (count($out_formated) > 1)
            return implode($glue, array_reverse(array_slice($out_formated, 1))) . $glue_last . $out_formated[0];
        else
            return $out_formated[0];
    }

}

