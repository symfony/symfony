<?php

/*
 * This file is part of the Symfony package.
 *
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
 *
 * @internal
 */
class Unescaper
{
    /**
     * Parser and Inline assume UTF-8 encoding, so escaped Unicode characters
     * must be converted to that encoding.
     *
     * @deprecated since version 2.5, to be removed in 3.0
     *
     * @internal
     */
    const ENCODING = 'UTF-8';

    /**
     * Regex fragment that matches an escaped character in a double quoted string.
     */
    const REGEX_ESCAPED_CHARACTER = '\\\\(x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|.)';

    /**
     * Unescapes a single quoted string.
     *
     * @param string $value A single quoted string
     *
     * @return string The unescaped string
     */
    public function unescapeSingleQuotedString($value)
    {
        return str_replace('\'\'', '\'', $value);
    }

    /**
     * Unescapes a double quoted string.
     *
     * @param string $value A double quoted string
     *
     * @return string The unescaped string
     */
    public function unescapeDoubleQuotedString($value)
    {
        $self = $this;
        $callback = function ($match) use ($self) {
            return $self->unescapeCharacter($match[0]);
        };

        // evaluate the string
        return preg_replace_callback('/'.self::REGEX_ESCAPED_CHARACTER.'/u', $callback, $value);
    }

    /**
     * Unescapes a character that was found in a double-quoted string.
     *
     * @param string $value An escaped character
     *
     * @return string The unescaped character
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code. Should be changed in 3.0.
     */
    public function unescapeCharacter($value)
    {
        switch ($value[1]) {
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
                return "\xB";
            case 'f':
                return "\xC";
            case 'r':
                return "\r";
            case 'e':
                return "\x1B";
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
                return "\xC2\x85";
            case '_':
                // U+00A0 NO-BREAK SPACE
                return "\xC2\xA0";
            case 'L':
                // U+2028 LINE SEPARATOR
                return "\xE2\x80\xA8";
            case 'P':
                // U+2029 PARAGRAPH SEPARATOR
                return "\xE2\x80\xA9";
            case 'x':
                return self::utf8chr(hexdec(substr($value, 2, 2)));
            case 'u':
                return self::utf8chr(hexdec(substr($value, 2, 4)));
            case 'U':
                return self::utf8chr(hexdec(substr($value, 2, 8)));
            default:
                @trigger_error('Not escaping a backslash in a double-quoted string is deprecated since Symfony 2.8 and will throw a ParseException in 3.0.', E_USER_DEPRECATED);

                return $value;
        }
    }

    /**
     * Get the UTF-8 character for the given code point.
     *
     * @param int $c The unicode code point
     *
     * @return string The corresponding UTF-8 character
     */
    private static function utf8chr($c)
    {
        if (0x80 > $c %= 0x200000) {
            return \chr($c);
        }
        if (0x800 > $c) {
            return \chr(0xC0 | $c >> 6).\chr(0x80 | $c & 0x3F);
        }
        if (0x10000 > $c) {
            return \chr(0xE0 | $c >> 12).\chr(0x80 | $c >> 6 & 0x3F).\chr(0x80 | $c & 0x3F);
        }

        return \chr(0xF0 | $c >> 18).\chr(0x80 | $c >> 12 & 0x3F).\chr(0x80 | $c >> 6 & 0x3F).\chr(0x80 | $c & 0x3F);
    }
}
