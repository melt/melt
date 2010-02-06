<?php

/**
*@desc The misc api namespace.
*/
class api_misc {
    /**
    * @desc Attempts to reset the output buffer to the default state
    * @desc by throwing away all buffered data and ending all stacked buffers.
    */
    public static function ob_reset() {
        // Remove previous buffers.
        $level = @ob_get_status(true);
        if (is_array($level)) {
            $level = $level['level'];
            for (;$level > 1; $level--) @ob_end_clean();
        }
        // Reset to default content type.
        if (!headers_sent())
            header('Content-Type: text/html');
        $ob_length = @ob_get_length();
        if (intval($ob_length) > 0)
            @ob_clean();
        @ob_implicit_flush(true);
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


    /**
    * @desc Returns TRUE if arrays are recursivly equal, otherwise FALSE.
    * @desc Also returns FALSE if any input is not of array type.
    */
    public static function compare_arrays($array1, $array2) {
        if (!is_array($array1) || !is_array($array2) || (count($array1) != count($array2)))
            return false;
        foreach ($array1 as $key => $val) {
            if (!isset($array2[$key]))
                return false;
            $val2 = $array2[$key];
            if (is_array($val)) {
                if (!api_misc::compare_arrays($val, $val2))
                    return false;
            } else if ($val != $val2) {
                return false;
            }
        }
        return true;
    }
}

?>