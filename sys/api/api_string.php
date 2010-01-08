<?php

define("SIMPLE_CRYPT_SALT", "\xf8\x87\x00\x63\x5a\x36\xf5\x19\xb6\x2a\x94\x4b\x09\x83\x41\x31");

/**
*@desc String handling helper functions api.
*/
class api_string {

    /** @desc Returns a random hexadecimal string. */
    public static function random_hex_str($length = 16) {
        $str = '';
        for ($i = 0; $i < $length; $i++)
            $str .= dechex(rand(0, 15));
        return $str;
    }

    /**
    * @desc Returns true if $str starts with $start.
    */
    public static function starts_with($str, $start) {
        if (strlen($str) < strlen($start))
            return false;
        return (substr($str, 0, strlen($start)) == $start);
    }

    /**
    * @desc Returns a string representation of given data.
    *       Function is guaranteed to be injective.
    * @return String A unique, deterministic representation of the input data consisting only of "A-Z a-z 0-9"
    */
    public static function str_represent($data) {
        $str = base64_encode($data);
        $str = rtrim($str, '=');
        $str = str_replace('a', 'ab', $str);
        $str = str_replace('+', 'ac', $str);
        $str = str_replace('/', 'ad', $str);
        return $str;
    }

    /**
    * @desc Returns the original string passed to str_represent.
    */
    public static function str_derepresent($str) {
        $str = str_replace('ad', '/', $str);
        $str = str_replace('ac', '+', $str);
        $str = str_replace('ab', 'a', $str);
        return base64_decode($str);
    }

    /**
    * @desc Returns true if given email address is RFC 2822 compatible.
    */
    public static function email_validate($email) {
        $emailregex = ";^((?>[a-zA-Z\d!#$%&'*+\-/=?^_`{|}~]+\x20*|\"((?=[\x01-\x7f])[^\"\\]|\\[\x01-\x7f])*\"\x20*)*(?<angle><))?((?!\.)(?>\.?[a-zA-Z\d!#$%&'*+\-/=?^_`{|}~]+)+|\"((?=[\x01-\x7f])[^\"\\]|\\[\x01-\x7f])*\")@(((?!-)[a-zA-Z\d\-]+(?<!-)\.)+[a-zA-Z]{2,}|\[(((?(?<!\[)\.)(25[0-5]|2[0-4]\d|[01]?\d?\d)){4}|[a-zA-Z\d\-]*[a-zA-Z\d]:((?=[\x01-\x7f])[^\\\[\]]|\\[\x01-\x7f])+)\])(?(angle)>)$;";
        return !(preg_match($emailregex, $email) == 0);
    }

    /**
    * @desc Generates a random key. The key is 16 bytes and can contain ANY 00-FF byte.
    */
    public static function gen_key() {
        return md5(mt_rand() . mt_rand() . "\x23\x73\x35\x56" . mt_rand() . mt_rand(), true);
    }

    /**
    * @desc Encrypts the cleartext, returns an encrypted base64 encoded string with a linear sized compared to input size.
    */
    public static function simple_crypt($cleartext, $password) {
        // Append verification.
        $cleartext .= md5(SIMPLE_CRYPT_SALT . $cleartext, true);
        // Append padding.
        $pad_length = 16 - (strlen($cleartext) % 16);
        if ($pad_length == 0)
            $pad_length = 16;
        $cleartext .= "\x01" . str_pad('', $pad_length - 1, "\x00");
        // The first block contains the salt.
        $crypttext = $block = md5(mt_rand() . mt_rand() . SIMPLE_CRYPT_SALT . mt_rand(), true);
        $pwdblock = $password;
        for ($i = 0; $i < strlen($cleartext); $i += 16) {
            // Transform password block using cleartext.
            $pwdblock = md5($block . SIMPLE_CRYPT_SALT . $pwdblock . $password, true);
            // Get cleartext block.
            $block = substr($cleartext, $i, 16);
            // Encrypt it.
            $crypttext .= $block ^ $pwdblock;
        }
        // Encode and return.
        return base64_encode($crypttext);
    }

    /**
    * @desc Decrypts the cryptotext outputted from simple_crypt with the given password.
    * @desc Returns FALSE if decryption failed.
    */
    public static function simple_decrypt($crypttext, $password) {
        // Decode.
        $crypttext = base64_decode($crypttext);
        // Need to contain at least 3 blocks.
        if (strlen($crypttext) < 3 * 16)
            return false;
        // Partial blocks not allowed.
        if ((strlen($crypttext) % 16) != 0)
            return false;
        // The first block contains the salt.
        $block = substr($crypttext, 0, 16);
        $pwdblock = $password;
        $cleartext = '';
        for ($i = 16; $i < strlen($crypttext); $i += 16) {
            // Transform password block using cleartext.
            $pwdblock = md5($block . SIMPLE_CRYPT_SALT . $pwdblock . $password, true);
            // Decrypt cryptext block.
            $cleartext .= $block = substr($crypttext, $i, 16) ^ $pwdblock;
        }
        // Remove padding.
        $pad_pos = strrpos($cleartext, "\x01");
        if ($pad_pos === false || $pad_pos < strlen($cleartext) - 16)
            return false;
        $cleartext = substr($cleartext, 0, $pad_pos);
        // Slice verification from cleartext.
        $verification = substr($cleartext, -16);
        $cleartext = substr($cleartext, 0, -16);
        // Verify and return result.
        return ($verification == md5(SIMPLE_CRYPT_SALT . $cleartext, true))? $cleartext: false;
    }

    /**
    * @desc Takes a text that is cased like this: fooBarLol and converts it to underlined form: foo_bar_lol
    */
    public static function cased_to_underline($text) {
        return strtolower(preg_replace('#([a-z])([A-Z])#', '\1_\2', $text));
    }

    /**
    * @desc Verifies that the given string length is within a certain range.
    */
    public static function in_range($string, $min = -1, $max = -1) {
        if (strlen($string) < $min)
            return false;
        else if ($max >= 0 && strlen($string) > $max)
            return false;
        else
            return true;
    }

}

?>
