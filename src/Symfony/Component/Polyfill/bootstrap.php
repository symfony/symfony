<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Polyfill\Functions as p;
use Symfony\Component\Intl\Globals\IntlGlobals;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */

if (PHP_VERSION_ID < 50400) {
    if (!function_exists('trait_exists')) {
        function trait_exists($class, $autoload = true) { return $autoload && class_exists($class, $autoload) && false; }
    }
    if (!function_exists('class_uses')) {
        function class_uses($class, $autoload = true) { return $autoload && class_exists($class, $autoload) && false; }
    }
    if (!function_exists('hex2bin')) {
        function hex2bin($data) { return p\Php54::hex2bin($data); }
    }
    if (!function_exists('session_register_shutdown')) {
        function session_register_shutdown() { register_shutdown_function('session_write_close'); }
    }
}

if (PHP_VERSION_ID < 50500) {
    if (!function_exists('boolval')) {
        function boolval($val) { return p\Php55::boolval($val); }
    }
    if (!function_exists('json_last_error_msg')) {
        function json_last_error_msg() { return p\Php55::json_last_error_msg(); }
    }
    if (!function_exists('array_column')) {
        function array_column($array, $columnKey, $indexKey = null) { return p\Php55ArrayColumn::array_column($array, $columnKey, $indexKey); }
    }
    if (!function_exists('hash_pbkdf2')) {
        function hash_pbkdf2($algorithm, $password, $salt, $iterations, $length = 0, $rawOutput = false) { return p\Php55::hash_pbkdf2($algorithm, $password, $salt, $iterations, $length, $rawOutput); }
    }
}

if (PHP_VERSION_ID < 50600) {
    if (!function_exists('hash_equals')) {
        function hash_equals($knownString, $userInput) { return p\Php56::hash_equals($knownString, $userInput); }
    }
    if (extension_loaded('ldap') && !function_exists('ldap_escape')) {
        define('LDAP_ESCAPE_FILTER', 1);
        define('LDAP_ESCAPE_DN', 2);

        function ldap_escape($subject, $ignore = '', $flags = 0) { return p\Php56::ldap_escape($subject, $ignore, $flags); }
    }
}

if (PHP_VERSION_ID < 70000) {
    if (!function_exists('intdiv')) {
        function intdiv($dividend, $divisor) { return p\Php70::intdiv($dividend, $divisor); }
    }
    if (!function_exists('preg_replace_callback_array')) {
        function preg_replace_callback_array(array $patterns, $subject, $limit = -1, &$count = 0) { return p\Php70::preg_replace_callback_array($patterns, $subject, $limit, $count); }
    }
    if (!function_exists('error_clear_last')) {
        function error_clear_last() { return p\Php70::error_clear_last(); }
    }
}

if (!function_exists('grapheme_strlen')) {
    define('GRAPHEME_EXTR_COUNT', 0);
    define('GRAPHEME_EXTR_MAXBYTES', 1);
    define('GRAPHEME_EXTR_MAXCHARS', 2);

    function grapheme_extract($s, $size, $type = 0, $start = 0, &$next = 0) { return p\Intl::grapheme_extract($s, $size, $type, $start, $next); }
    function grapheme_stripos($s, $needle, $offset = 0) { return p\Intl::grapheme_stripos($s, $needle, $offset); }
    function grapheme_stristr($s, $needle, $beforeNeedle = false) { return p\Intl::grapheme_stristr($s, $needle, $beforeNeedle); }
    function grapheme_strlen($s) { return p\Intl::grapheme_strlen($s); }
    function grapheme_strpos($s, $needle, $offset = 0) { return p\Intl::grapheme_strpos($s, $needle, $offset); }
    function grapheme_strripos($s, $needle, $offset = 0) { return p\Intl::grapheme_strripos($s, $needle, $offset); }
    function grapheme_strrpos($s, $needle, $offset = 0) { return p\Intl::grapheme_strrpos($s, $needle, $offset); }
    function grapheme_strstr($s, $needle, $beforeNeedle = false) { return p\Intl::grapheme_strstr($s, $needle, $beforeNeedle); }
    function grapheme_substr($s, $start, $len = 2147483647) { return p\Intl::grapheme_substr($s, $start, $len); }
}
if (!function_exists('normalizer_is_normalized')) {
    function normalizer_is_normalized($s, $form = p\Normalizer::NFC) { return p\Normalizer::isNormalized($s, $form); }
    function normalizer_normalize($s, $form = p\Normalizer::NFC) { return p\Normalizer::normalize($s, $form); }
}

if (!function_exists('mb_strlen')) {
    define('MB_CASE_UPPER', 0);
    define('MB_CASE_LOWER', 1);
    define('MB_CASE_TITLE', 2);

    function mb_convert_encoding($s, $to, $from = null) { return p\Mbstring::mb_convert_encoding($s, $to, $from); }
    function mb_decode_mimeheader($s) { return p\Mbstring::mb_decode_mimeheader($s); }
    function mb_encode_mimeheader($s, $charset = null, $transferEnc = null, $lf = null, $indent = null) { return p\Mbstring::mb_encode_mimeheader($s, $charset, $transferEnc, $lf, $indent); }
    function mb_convert_case($s, $mode, $enc = null) { return p\Mbstring::mb_convert_case($s, $mode, $enc); }
    function mb_internal_encoding($enc = null) { return p\Mbstring::mb_internal_encoding($enc); }
    function mb_language($lang = null) { return p\Mbstring::mb_language($lang); }
    function mb_list_encodings() { return p\Mbstring::mb_list_encodings(); }
    function mb_encoding_aliases($encoding) { return p\Mbstring::mb_encoding_aliases($encoding); }
    function mb_check_encoding($var = null, $encoding = null) { return p\Mbstring::mb_check_encoding($var, $encoding); }
    function mb_detect_encoding($str, $encodingList = null, $strict = false) { return p\Mbstring::mb_detect_encoding($str, $encodingList, $strict); }
    function mb_detect_order($encodingList = null) { return p\Mbstring::mb_detect_order($encodingList); }
    function mb_parse_str($s, &$result = array()) { parse_str($s, $result); }
    function mb_strlen($s, $enc = null) { return p\Mbstring::mb_strlen($s, $enc); }
    function mb_strpos($s, $needle, $offset = 0, $enc = null) { return p\Mbstring::mb_strpos($s, $needle, $offset, $enc); }
    function mb_strtolower($s, $enc = null) { return p\Mbstring::mb_strtolower($s, $enc); }
    function mb_strtoupper($s, $enc = null) { return p\Mbstring::mb_strtoupper($s, $enc); }
    function mb_substitute_character($char = null) { return p\Mbstring::mb_substitute_character($char); }
    function mb_substr($s, $start, $length = 2147483647, $enc = null) { return p\Mbstring::mb_substr($s, $start, $length, $enc); }
    function mb_stripos($s, $needle, $offset = 0, $enc = null) { return p\Mbstring::mb_stripos($s, $needle, $offset, $enc); }
    function mb_stristr($s, $needle, $part = false, $enc = null) { return p\Mbstring::mb_stristr($s, $needle, $part, $enc); }
    function mb_strrchr($s, $needle, $part = false, $enc = null) { return p\Mbstring::mb_strrchr($s, $needle, $part, $enc); }
    function mb_strrichr($s, $needle, $part = false, $enc = null) { return p\Mbstring::mb_strrichr($s, $needle, $part, $enc); }
    function mb_strripos($s, $needle, $offset = 0, $enc = null) { return p\Mbstring::mb_strripos($s, $needle, $offset, $enc); }
    function mb_strrpos($s, $needle, $offset = 0, $enc = null) { return p\Mbstring::mb_strrpos($s, $needle, $offset, $enc); }
    function mb_strstr($s, $needle, $part = false, $enc = null) { return p\Mbstring::mb_strstr($s, $needle, $part, $enc); }
    function mb_get_info($type = 'all') { return p\Mbstring::mb_get_info($type); }
    function mb_http_output($enc = null) { return p\Mbstring::mb_http_output($enc); }
    function mb_strwidth($s, $enc = null) { return p\Mbstring::mb_strwidth($s, $enc); }
    function mb_substr_count($haystack, $needle, $enc = null) { return p\Mbstring::mb_substr_count($haystack, $needle, $enc); }
    function mb_output_handler($contents, $status) { return p\Mbstring::mb_output_handler($contents, $status); }
    function mb_http_input($type = '') { return p\Mbstring::mb_http_input($type); }
    function mb_convert_variables($toEncoding, $fromEncoding, &$a = null, &$b = null, &$c = null, &$d = null, &$e = null, &$f = null) { return p\Mbstring::mb_convert_variables($toEncoding, $fromEncoding, $v0, $a, $b, $c, $d, $e, $f); }
}

if (!function_exists('utf8_encode')) {
    function utf8_encode($s) { return p\Xml::utf8_encode($s); }
    function utf8_decode($s) { return p\Xml::utf8_decode($s); }
}

if (!function_exists('intl_is_failure')) {
    function intl_is_failure($errorCode) { return IntlGlobals::isFailure($errorCode); }
    function intl_get_error_code() { return IntlGlobals::getErrorCode(); }
    function intl_get_error_message() { return IntlGlobals::getErrorMessage(); }
    function intl_error_name($errorCode) { return IntlGlobals::getErrorName($errorCode); }
}
