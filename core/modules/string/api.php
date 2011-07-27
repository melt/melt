<?php namespace melt\string;

/** Returns a string from a numeric index. Eg 0 = a, 1 = b... */
function from_index($index) {
    // Optimization.
    if ($index <= 0)
        return 'A';
    $out = "";
    $index = intval($index);
    while (true) {
        $char_index = $index % 26;
        $out .= chr(0x41 + $char_index);
        $index = ($index - $char_index - 1) / 26;
        if ($index < 0)
            return $out;
    }
}

/** Returns a random alpha-numeric string. */
function random_alphanum_str($length = 16, $case_sensitive = true) {
    $str = '';
    if ($case_sensitive) {
        for ($i = 0; $i < $length; $i++) {
            $r = mt_rand(0, 61);
            if ($r < 26) // A-Z
                $str .= chr(0x41 + $r);
            else if ($r < 52) // a-z
                $str .= chr(0x61 + $r - 26);
            else // 0-9
                $str .= chr(0x30 + $r - 52);
        }
    } else {
        for ($i = 0; $i < $length; $i++) {
            $r = mt_rand(0, 35);
            if ($r < 26) // A-Z
                $str .= chr(0x41 + $r);
            else // 0-9
                $str .= chr(0x30 + $r - 26);
        }
    }
    return $str;
}

/** Returns a random hexadecimal string. */
function random_hex_str($length = 16) {
    $str = '';
    for ($i = 0; $i < $length; $i++)
        $str .= dechex(mt_rand(0, 15));
    return $str;
}

/** Returns a random string that can contain ANY characher. */
function random_str($length = 16) {
    $str = '';
    for ($i = 0; $i < $length; $i++)
        $str .= chr(mt_rand(0, 255));
    return $str;
}

/**  Returns true if $subject starts with $prefix. */
function starts_with($subject, $prefix) {
    if (\strlen($subject) < \strlen($prefix))
        return false;
    return (\substr($subject, 0, \strlen($prefix)) === $prefix);
}

/**
 * @desc Returns true if $subject ends with $tail.
 */
function ends_with($subject, $tail) {
    if (\strlen($subject) < \strlen($tail))
        return false;
    return (\substr($subject, -\strlen($tail)) === $tail);
}

/**
 * Base64 alternative encoder that only uses alphanumeric charachers.
 * The tradeoff is worse performance.
 */
function base64_alphanum_encode($data) {
    $str = base64_encode($data);
    $str = rtrim($str, '=');
    $str = str_replace('a', 'ab', $str);
    $str = str_replace('+', 'ac', $str);
    $str = str_replace('/', 'ad', $str);
    return $str;
}

/**
 * Base64 alternative decoder that only uses alphanumeric charachers.
 * The tradeoff is worse performance.
 */
function base64_alphanum_decode($str) {
    $str = str_replace('ad', '/', $str);
    $str = str_replace('ac', '+', $str);
    $str = str_replace('ab', 'a', $str);
    return base64_decode($str);
}

/**
 * Hex encodes the given string.
 * @param string $str
 * @return string
 */
function hex_encode($str) {
    $hex_str = "";
    for ($i = 0; $i < strlen($str); $i++) {
        $ord = ord($str[$i]);
        if ($ord < 0x10)
            $hex_str .= "0";
        $hex_str .= dechex($ord);
    }
    return $hex_str;
}

/**
 * Hex decodes the given string.
 * String corruption, such as odd length or invalid charachers,
 * will be silently ignored.
 * @param string $str
 * @return string
 */
function hex_decode($str) {
    $new_str = "";
    for ($i = 0; $i < strlen($str); $i += 2)
        $new_str .= chr(hexdec(substr($str, $i, 2)));
    return $new_str;

}

/**
* @desc Returns true if given email address is RFC 2822 compatible.
*/
function email_validate($email) {
    $emailregex = ";^(([a-zA-Z\d!#$%&'*+\-/=?^_`{|}~]+\x20*|\"((?=[\x01-\x7f])[^\"\\]|\\[\x01-\x7f])*\"\x20*)*(?<angle><))?((?!\.)(\.?[a-zA-Z\d!#$%&'*+\-/=?^_`{|}~]+)+|\"((?=[\x01-\x7f])[^\"\\]|\\[\x01-\x7f])*\")@(((?!-)[a-zA-Z\d\-]+(?<!-)\.)+[a-zA-Z]{2,}|\[(((?(?<!\[)\.)(25[0-5]|2[0-4]\d|[01]?\d?\d)){4}|[a-zA-Z\d\-]*[a-zA-Z\d]:((?=[\x01-\x7f])[^\\\[\]]|\\[\x01-\x7f])+)\])(?(angle)>)$;";
    return preg_match($emailregex, $email) == 1;
}

/**
 * @desc Returns true if given HTTP/HTTPS URL looks valid.
 */
function http_url_validate($url) {
    $urlregex = '`^https?://'
    . '[0-9a-z-.]+' // hostname, can be "any" combination of theese charachers
    . '(:[0-9]{1,5})?' // port number- :80
    . '((/?)|' // a slash isn't required if there is no file name
    . '(/[0-9a-z_!~*\'().;?:@&=+$,%#-]+)+/?)$`';
    return preg_match($urlregex, $url) == 1;
}

const SIMPLE_CRYPT_SALT = "\xf8\x87\x00\x63\x5a\x36\xf5\x19\xb6\x2a\x94\x4b\x09\x83\x41\x31";
const SIMPLE_CRYPT_BLOCKSIZE = 20;

/**
 * Encrypts the cleartext, returns an encrypted base64 encoded string.
 * The output length is not equal but linear to the input size.
 * @param string $cleartext Cleartext to encrypt.
 * @param string $password Password to encrypt with, or null if you want
 * simple_crypt to generate and use a random session local key instead.
 */
function simple_crypt($cleartext, $password = null) {
    if ($password === null) {
        if (!isset($_SESSION['simple_crypt_key']) || strlen($_SESSION['simple_crypt_key']) < SIMPLE_CRYPT_BLOCKSIZE)
            $_SESSION['simple_crypt_key'] = random_str(SIMPLE_CRYPT_BLOCKSIZE);
        $password = $_SESSION['simple_crypt_key'];
    }
    // Append verification.
    $cleartext .= sha1(SIMPLE_CRYPT_SALT . $cleartext, true);
    // Append padding.
    $pad_length = SIMPLE_CRYPT_BLOCKSIZE - (strlen($cleartext) % SIMPLE_CRYPT_BLOCKSIZE);
    if ($pad_length == 0)
        $pad_length = SIMPLE_CRYPT_BLOCKSIZE;
    $cleartext .= "\x01" . str_pad('', $pad_length - 1, "\x00");
    // The first block contains the salt.
    $crypttext = $block = sha1(mt_rand() . mt_rand() . SIMPLE_CRYPT_SALT . mt_rand(), true);
    $pwdblock = $password;
    for ($i = 0; $i < strlen($cleartext); $i += SIMPLE_CRYPT_BLOCKSIZE) {
        // Transform password block using cleartext.
        $pwdblock = sha1($block . SIMPLE_CRYPT_SALT . $pwdblock . $password, true);
        // Get cleartext block.
        $block = substr($cleartext, $i, SIMPLE_CRYPT_BLOCKSIZE);
        // Encrypt it.
        $crypttext .= $block ^ $pwdblock;
    }
    // Encode and return.
    return base64_alphanum_encode($crypttext);
}

/**
 * Decrypts the cryptotext outputted from simple_crypt with the given password.
 * Returns FALSE if decryption failed.
 * @param string $password Password to encrypt with, or null if it was
 * encrypted with the session key.
 * @return mixed FALSE if decryption failed, otherwise the decrypted string.
 */
function simple_decrypt($crypttext, $password = null) {
    if ($password === null)
        $password = @$_SESSION['simple_crypt_key'];
    // Decode.
    $crypttext = base64_alphanum_decode($crypttext);
    // Need to contain at least 3 blocks.
    if (strlen($crypttext) < 3 * SIMPLE_CRYPT_BLOCKSIZE)
        return false;
    // Partial blocks not allowed.
    if ((strlen($crypttext) % SIMPLE_CRYPT_BLOCKSIZE) != 0)
        return false;
    // The first block contains the salt.
    $block = substr($crypttext, 0, SIMPLE_CRYPT_BLOCKSIZE);
    $pwdblock = $password;
    $cleartext = '';
    for ($i = SIMPLE_CRYPT_BLOCKSIZE; $i < strlen($crypttext); $i += SIMPLE_CRYPT_BLOCKSIZE) {
        // Transform password block using cleartext.
        $pwdblock = sha1($block . SIMPLE_CRYPT_SALT . $pwdblock . $password, true);
        // Decrypt cryptext block.
        $cleartext .= $block = substr($crypttext, $i, SIMPLE_CRYPT_BLOCKSIZE) ^ $pwdblock;
    }
    // Remove padding.
    $pad_pos = strrpos($cleartext, "\x01");
    if ($pad_pos === false || $pad_pos < strlen($cleartext) - SIMPLE_CRYPT_BLOCKSIZE)
        return false;
    $cleartext = substr($cleartext, 0, $pad_pos);
    // Slice verification from cleartext.
    $verification = substr($cleartext, -SIMPLE_CRYPT_BLOCKSIZE);
    $cleartext = substr($cleartext, 0, -SIMPLE_CRYPT_BLOCKSIZE);
    // Verify and return result.
    return ($verification == sha1(SIMPLE_CRYPT_SALT . $cleartext, true))? $cleartext: false;
}

/**
* @desc Takes a text that is cased like this: fooBar and converts it to underlined form: foo_bar
*/
function cased_to_underline($text) {
    return \melt\internal\cased_to_underline($text);
}

/**
 * @desc Takes a text that is underlined like this: foo_bar and converts it to cased form: FooBar
 */
function underline_to_cased($text) {
    return \melt\internal\underline_to_cased($text);
}

/**
* @desc Verifies that the given string length is within a certain range.
*/
function in_range($string, $min = -1, $max = -1) {
    if (strlen($string) < $min)
        return false;
    else if ($max >= 0 && strlen($string) > $max)
        return false;
    else
        return true;
}

/**
 * Converts variable to string and returns it quoted.
 * The following charachers with special meaning in many contexts
 * is automatically escaped: control charachers (<0x20)
 * " ' < > \ $
 * @param string $string
 */
function quote($string) {
    return '"' . \preg_replace_callback('#[\x00-\x1f\x22\x24\x27\x3c\x3e\x5c]#', function($matches) {
        return '\x' . \str_pad(\dechex(\ord($matches[0])), 2, "0", \STR_PAD_LEFT);
    }, (string) $string) . '"';
}

/**
 * Calculates the least common substring of two strings.
 * It has a worst case of O(strlen($str1) * strlen($str2))
 * but should usually run much faster if much_reach_least is high enough.
 * @param string $str1
 * @param string $str2
 * @param integer $must_reach_least
 * @return integer LCS distance or FALSE if must_reach_least canceled the search.
 */
function lcs_distance($str1, $str2, $must_reach_least = null) {
    // Reduce all charachers that exists in neither string,
    // This should not affect the result and should improve many real world cases.
    $str1_chars = \count_chars($str1, 1);
    $str2_chars = \count_chars($str2, 1);
    $unique_chars = \array_keys(\array_diff_key($str1_chars, $str2_chars) + \array_diff_key($str2_chars, $str1_chars));
    $unique_chars = \array_map(function($c) { return \chr($c); }, $unique_chars);
    $str1 = \str_replace($unique_chars, array(), $str1);
    $str2 = \str_replace($unique_chars, array(), $str2);
    // Take distances into account.
    $m = \strlen($str1);
    $n = \strlen($str2);
    if ($must_reach_least !== null) {
        $max_lcs = \max($m, $n);
        if ($max_lcs < $must_reach_least)
            return false;
        else if ($max_lcs === $must_reach_least)
            return \strcmp($str1, $str2) === 0? $max_lcs: false;
    }
    $lcs_row_a = \array_fill(0, $n + 1, 0);
    $lcs_row_b = \array_fill(0, $n + 1, 0);
    // This variable holds the max lcs for the current row.
    // It is equal to the maximum value on row above + 1.
    $max_row_lcs = 1;
    for ($i = 1; $i <= $m; $i++) {
        // Reduces array copying in memory by flipping references.
        if (($i % 2) == 0) {
            $lcs_row_above =& $lcs_row_a;
            $lcs_table_current =& $lcs_row_b;
        } else {
            $lcs_row_above =& $lcs_row_b;
            $lcs_table_current =& $lcs_row_a;
        }
        // Make comparision that short circuits if max lcs is reached.
        for ($j = 1; $j <= $n; $j++) {
            if ($str1[$i - 1] == $str2[$j - 1])
                $new_lcs = $lcs_row_above[$j - 1] + 1;
            else
                $new_lcs = \max($lcs_table_current[$j - 1], $lcs_row_above[$j]);
            $lcs_table_current[$j] = $new_lcs;
            if ($new_lcs == $max_row_lcs) {
                // Max lcs is reached, row can no longer increment - so just fill.
                $max_row_lcs++;
                for ($j++; $j <= $n; $j++)
                    $lcs_table_current[$j] = $new_lcs;
                break;
            }
        }
        if ($must_reach_least !== null) {
            // Check that LCS can still reach at least value.
            $max_lcs_possible = ($m - $i) + ($max_row_lcs - 1);
            if ($max_lcs_possible < $must_reach_least)
                return false;
        }
    }
    return $max_row_lcs - 1;
}

/**
 * Calculates the lcs similarity between string 1 and string 2 and returns
 * it. The similarity is a float % calculated like:
 * lcs_distance(str1, str2) / max(strlen(str1), strlen(str2))
 * It has a worst case runtime of O(strlen(str1) * strlen(str2))
 * Setting a minimum similarity will speed up the search.
 * @param string $str1
 * @param string $str2
 * @param float $minimum_similarity
 * @return mixed Either a float if similarity was calculated or
 * FALSE if the calculation was canceled because the similarity is below
 * $minimum_similarity.
 */
function lcs_similarity($str1, $str2, $minimum_similarity = null) {
    $max_lcs = \max(\strlen($str1), \strlen($str2));
    if ($minimum_similarity === null) {
        $must_reach_least = null;
    } else {
        $minimum_similarity = ($minimum_similarity < 0)? 0: ($minimum_similarity > 1? 1: $minimum_similarity);
        $must_reach_least = \floor($max_lcs * \floatval($minimum_similarity));
    }
    $dist = lcs_distance($str1, $str2, $must_reach_least);
    if ($dist === false)
        return false;
    else
        return $dist / $max_lcs;
}