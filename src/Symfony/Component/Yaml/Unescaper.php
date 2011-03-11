<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml;

/**
 * Unescaper encapsulates unescaping rules for single and double-quoted
 * YAML strings.
 *
 * @author Matthew Lewinski <matthew@lewinski.org>
 */
class Unescaper
{
    // Parser and Inline assume UTF-8 encoding, so escaped Unicode characters
    // must be converted to that encoding.
    const ENCODING = 'UTF-8';

    // Regex fragment that matches an escaped character in a double quoted
    // string.
    const REGEX_ESCAPED_CHARACTER = "\\\\([0abt\tnvfre \\\"\\/\\\\N_LP]|x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8})";

    /**
     * Unescapes a single quoted string.
     *
     * @param string $value A single quoted string.
     *
     * @return string The unescaped string.
     */
    public function unescapeSingleQuotedString($value)
    {
        return str_replace('\'\'', '\'', $value);
    }

    /**
     * Unescapes a double quoted string.
     *
     * @param string $value A double quoted string.
     *
     * @return string The unescaped string.
     */
    public function unescapeDoubleQuotedString($value)
    {
        $self = $this;
        $callback = function($match) use($self) {
            return $self->unescapeCharacter($match[0]);
        };

        // evaluate the string
        return preg_replace_callback('/'.self::REGEX_ESCAPED_CHARACTER.'/u', $callback, $value);
    }

    /**
     * Unescapes a character that was found in a double-quoted string
     *
     * @param string $value An escaped character
     *
     * @return string The unescaped character
     */
    public function unescapeCharacter($value)
    {
        switch ($value{1}) {
            case '0':
                return "\x0";
            case 'a':
                return "\x7";
            case 'b':
                return "\x8";
            case 't':
                return "\t";
            case "\t":
                return "\t";
            case 'n':
                return "\n";
            case 'v':
                return "\xb";
            case 'f':
                return "\xc";
            case 'r':
                return "\xd";
            case 'e':
                return "\x1b";
            case ' ':
                return ' ';
            case '"':
                return '"';
            case '/':
                return '/';
            case '\\':
                return '\\';
            case 'N':
                // U+0085 NEXT LINE
                return $this->convertEncoding("\x00\x85", self::ENCODING, 'UCS-2BE');
            case '_':
                // U+00A0 NO-BREAK SPACE
                return $this->convertEncoding("\x00\xA0", self::ENCODING, 'UCS-2BE');
            case 'L':
                // U+2028 LINE SEPARATOR
                return $this->convertEncoding("\x20\x28", self::ENCODING, 'UCS-2BE');
            case 'P':
                // U+2029 PARAGRAPH SEPARATOR
                return $this->convertEncoding("\x20\x29", self::ENCODING, 'UCS-2BE');
            case 'x':
                $char = pack('n', hexdec(substr($value, 2, 2)));
                return $this->convertEncoding($char, self::ENCODING, 'UCS-2BE');
            case 'u':
                $char = pack('n', hexdec(substr($value, 2, 4)));
                return $this->convertEncoding($char, self::ENCODING, 'UCS-2BE');
            case 'U':
                $char = pack('N', hexdec(substr($value, 2, 8)));
                return $this->convertEncoding($char, self::ENCODING, 'UCS-4BE');
        }
    }

    /**
     * Convert a string from one encoding to another.
     *
     * @param string $string The string to convert
     * @param string $to     The input encoding
     * @param string $from   The output encoding
     *
     * @return string The string with the new encoding
     *
     * @throws \RuntimeException if no suitable encoding function is found (iconv or mbstring)
     */
    private function convertEncoding($value, $to, $from)
    {
        if (function_exists('iconv')) {
            return iconv($from, $to, $value);
        } elseif (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, $to, $from);
        }

        throw new \RuntimeException('No suitable convert encoding function (install the iconv or mbstring extension).');
    }
}
