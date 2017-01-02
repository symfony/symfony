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

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Util\StringReader;

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
     * Unescapes a single quoted string.
     *
     * @return string The unescaped string
     */
    public function unescapeSingleQuotedString(StringReader $reader)
    {
        $value = $reader->readCSpan('\'');
        $reader->expectChar('\'');

        while ($reader->readChar('\'')) {
            $value .= '\'';
            $value .= $reader->readCSpan('\'');
            $reader->expectChar('\'');
        }

        return $value;
    }

    /**
     * Unescapes a double quoted string.
     *
     * @return string The unescaped string
     */
    public function unescapeDoubleQuotedString(StringReader $reader)
    {
        $value = $reader->readCSpan('"\\');
        while (true) {
            if ($reader->readChar('\\')) {
                $value .= $this->unescapeCharacter($reader);
            } else {
                $reader->expectChar('"');
                break;
            }

            $value .= $reader->readCSpan('"\\');
        }

        return $value;
    }

    /**
     * Unescapes a character that was found in a double-quoted string.
     *
     * @param string $value An escaped character
     *
     * @return string The unescaped character
     */
    private function unescapeCharacter(StringReader $reader)
    {
        $character = $reader->read(1);
        switch ($character) {
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
                return self::utf8chr(hexdec($reader->read(2)));
            case 'u':
                return self::utf8chr(hexdec($reader->read(4)));
            case 'U':
                return self::utf8chr(hexdec($reader->read(8)));
            default:
                throw new ParseException(sprintf('Found unknown escape character "\\%s".', $character));
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
            return chr($c);
        }
        if (0x800 > $c) {
            return chr(0xC0 | $c >> 6).chr(0x80 | $c & 0x3F);
        }
        if (0x10000 > $c) {
            return chr(0xE0 | $c >> 12).chr(0x80 | $c >> 6 & 0x3F).chr(0x80 | $c & 0x3F);
        }

        return chr(0xF0 | $c >> 18).chr(0x80 | $c >> 12 & 0x3F).chr(0x80 | $c >> 6 & 0x3F).chr(0x80 | $c & 0x3F);
    }
}
