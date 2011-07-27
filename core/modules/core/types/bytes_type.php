<?php namespace melt\core;

class BytesType extends \melt\AppType {
    /** @var boolean Displaying with SI units by default. Set to
     * false to display with IEC units instead. */
    public $display_si = true;
    /** @var boolean If this is false, a larger unit will not be used
     * if using it results in less precision. */
    public $display_rounding = false;
    /** @var integer Maximum number of decimals or decimals to round
     * to when using display rounding. */
    public $display_max_decimals = 2;


    public function getSQLType() {
        return "bigint";
    }

    public function getSQLValue() {
        return intval($this->value);
    }

    public function getInterface($name) {
        $value = escape(self::byteUnit($this->value));
        return "<input type=\"text\" name=\"$name\" id=\"$name\" value=\"$value\" />";
    }

    public function addBytes($bytes) {
        $this->value += $bytes;
    }

    public function set($value) {
        $this->value = \intval($value);
    }

    public function readInterface($name) {
        $value = @$_POST[$name];
        $number =  \floatval($value);
        $text = \preg_replace("#[^A-Z]#", "", \strtoupper($value));
        $units = array(
            "B" => 1,
            "KB" => 1000,
            "MB" => \pow(1000, 2),
            "GB" => \pow(1000, 3),
            "TB" => \pow(1000, 4),
            "PB" => \pow(1000, 5),
            "EB" => \pow(1000, 6),
            "ZB" => \pow(1000, 7),
            "YB" => \pow(1000, 8),
            "KIB" => 1024,
            "MIB" => \pow(1024, 2),
            "GIB" => \pow(1024, 3),
            "TIB" => \pow(1024, 4),
            "PIB" => \pow(1024, 5),
            "EIB" => \pow(1024, 6),
            "ZIB" => \pow(1024, 7),
            "YIB" => \pow(1024, 8),
        );
        $scale = isset($units[$text])? $units[$text]: 1;
        $this->value = \intval(\abs($number) * $scale);
    }

    public function __toString() {
        return self::byteUnit(\intval($this->value), $this->display_si, $this->display_rounding, $this->display_max_decimals);
    }

    /**
     * @param integer $byte_count Number of bytes.
     * @param boolean $si Set this to false to use IEC standard size notation
     * instead of the SI notation. (SI: 1000 b/Kb, IEC: 1024 b/KiB)
     * @param integer $max_decimals Maximum number of decimals in result.
     * @return string The number of bytes in a readable unit representation.
     */
    public static function byteUnit($byte_count, $si = true, $rounding = false, $max_decimals = 2) {
        $byte_count = \intval($byte_count);
        if ($si) {
            $base_unit = 1000;
            $unit_array = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
        } else {
            $base_unit = 1024;
            $unit_array = array("B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB");
        }
        $ret_fn = function($byte_count, $unit) use ($rounding, $max_decimals) {
            if ($rounding)
                $byte_count = \round($byte_count, $max_decimals);
            return \strval($byte_count) . " " . $unit;
        };
        foreach ($unit_array as $unit) {
            if ($byte_count < $base_unit)
                return $ret_fn($byte_count, $unit);
            $byte_count /= $base_unit;
            if (!$rounding) {
                // Verify that not too many decimals are used now.
                $point_pos = \strpos($byte_count, ".");
                if ($point_pos !== false) {
                    $decimals = \strlen(\substr($byte_count, $point_pos + 1));
                    if ($decimals > $max_decimals)
                        return $ret_fn($byte_count * $base_unit, $unit);
                }
            }
        }
        $byte_count *= $base_unit;
        return $ret_fn($byte_count, $unit);
    }
}


