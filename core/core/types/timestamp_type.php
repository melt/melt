<?php namespace melt\core;

class TimestampType extends \melt\AppType {
    public $only_date = "false";
    public $title = "";

    public function set($value) {
        parent::set(($value instanceof \DateTime)? $value->getTimestamp(): \intval($value));
    }

    public function getSQLType() {
        return "bigint";
    }

    public function getSQLValue() {
        return \intval($this->value);
    }

    public function getInterface($name) {
        $title = ($this->title != "")? ' title="' . $this->title . '" ': '';
        $dateonly = $this->only_date == "true";
        $date_syntax_helper = __('YYYY-MM-DD');
        $time_syntax_helper = __(', HH:MM:SS');
        $stamp = ($this->value != 0)? (string) $this: $date_syntax_helper;
        if (!$dateonly && $this->value == 0)
            $stamp .= $time_syntax_helper;
        return "<input$title type=\"text\" name=\"$name\" id=\"$name\" value=\"$stamp\" />"
            . "<br /><span style=\"font-size: 9px;\">" . __('Timestamp Format')
            . ": " . $date_syntax_helper . ($dateonly? "": $time_syntax_helper) . "</span>";
    }

    public function readInterface($name) {
        $newstamp = \strval(@$_POST[$name]);
        // Get the numeric clusters.
        $m = \preg_split('#[^0-9]+#', $newstamp);
        // Filter all empty positions.
        $m = \array_values(\array_filter($m, function($val) { return $val !== ''; }));
        // Make timestamp.
        $dateonly = $this->only_date == "true";
        if ($dateonly) {
            if (\count($m) == 3) {
                $yr = \intval($m[0]);
                $mo = \intval($m[1]);
                $d = \intval($m[2]);
                $time = \mktime(0, 0, 0, $mo, $d, $yr);
                if ($time === false || $time === -1)
                    $time = 0;
            } else
                $time = 0;
        } else {
            if (\count($m) == 6) {
                $yr = \intval($m[0]);
                $mo = \intval($m[1]);
                $d = \intval($m[2]);
                $hr = \intval($m[3]);
                $mi = \intval($m[4]);
                $s = \intval($m[5]);
                $time = \mktime($hr, $mi, $s, $mo, $d, $yr);
                if ($time === false || $time === -1)
                    $time = 0;
            } else
                $time = 0;
        }
        $this->value = intval($time);
    }

    public function __toString() {
        $value = \intval($this->value);
        if ($value == 0)
            return "";
        $dateonly = $this->only_date == "true";
        return \date(!$dateonly? 'Y-m-d, H:i:s e': 'Y-m-d', $value);
    }

}

