<?php

namespace nanomvc\core;

class BytesType extends \nanomvc\Type {
    public function getSQLType() {
        return "int";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name, $label) {
        $value = escape($this->value . " B");
        return "$label <input type=\"text\" name=\"$name\" value=\"$value\" />";
    }

    public function addBytes($bytes) {
        $this->value += $bytes;
    }

    public function readInterface($name) {
        $value = @$_POST[$name];
        $number =  floatval($value);
        $text = preg_replace("#[^A-Z]#", "", strtoupper($value));
        $units = array(
            "B" => 1,
            "KB" => 1000,
            "MB" => pow(1000, 2),
            "GB" => pow(1000, 3),
            "TB" => pow(1000, 4),
            "PB" => pow(1000, 5),
            "EB" => pow(1000, 6),
            "ZB" => pow(1000, 7),
            "YB" => pow(1000, 8),
            "KIB" => 1024,
            "MIB" => pow(1024, 2),
            "GIB" => pow(1024, 3),
            "TIB" => pow(1024, 4),
            "PIB" => pow(1024, 5),
            "EIB" => pow(1024, 6),
            "ZIB" => pow(1024, 7),
            "YIB" => pow(1024, 8),
        );
        $scale = isset($units[$text])? $units[$text]: 1;
        $this->value = intval(abs($number) * $scale);
    }

    public function __toString() {
        return self::byte_unit(intval($this->value));
    }

    /**
    * @param Integer $byte Number of bytes.
    * @param Boolean $si Set this to false to use IEC standard size notation instead of the SI notation. (SI: 1000 b/Kb, IEC: 1024 b/KiB)
    * @return String The number of bytes in a readable unit representation.
    */
    public static function byte_unit($byte, $si = true) {
        $byte = intval($byte);
        if ($si) {
            $u = 1000;
            $uarr = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
        } else {
            $u = 1024;
            $uarr = array("B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB");
        }
        foreach ($uarr as $unit) {
            if ($byte < $u) return strval(round($byte, 2)) . " " . $unit;
            else $byte /= $u;
        }
        return strval(round($byte, 2)) . " " . $uarr[count($uarr) - 1];
    }
}


