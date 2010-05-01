<?php

namespace nanomvc\string;


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
    if (strlen($subject) < strlen($prefix))
        return false;
    return (substr($subject, 0, strlen($prefix)) == $prefix);
}

/**
 * @desc Returns true if $subject ends with $tail.
 */
function ends_with($subject, $tail) {
    if (strlen($subject) < strlen($tail))
        return false;
    return (substr($subject, -strlen($tail)) == $tail);
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
        $password = $_SESSION['simple_crypt_key'];
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
    return \nanomvc\internal\cased_to_underline($text);
}

/**
 * @desc Takes a text that is underlined like this: foo_bar and converts it to cased form: FooBar
 */
function underline_to_cased($text) {
    return \nanomvc\internal\underline_to_cased($text);
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

