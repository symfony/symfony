<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * HTTP header utility functions.
 *
 * @author Christian Schmidt <github@chsc.dk>
 */
class HeaderUtils
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Splits an HTTP header by one or more separators.
     *
     * Example:
     *
     *     HeaderUtils::split("da, en-gb;q=0.8", ",;")
     *     // => array(array('da'), array('en-gb', 'q=0.8'))
     *
     * @param string $header     HTTP header value
     * @param string $separators List of characters to split on, ordered by
     *                           precedence, e.g. ",", ";=", or ",;="
     *
     * @return array Nested array with as many levels as there are characters in
     *               $separators
     */
    public static function split(string $header, string $separators): array
    {
        $quotedSeparators = preg_quote($separators, '/');

        preg_match_all('
            /
                (?!\s)
                    (?:
                        # quoted-string
                        "(?:[^"\\\\]|\\\\.)*(?:"|\\\\|$)
                    |
                        # token
                        [^"'.$quotedSeparators.']+
                    )+
                (?<!\s)
            |
                # separator
                \s*
                (?<separator>['.$quotedSeparators.'])
                \s*
            /x', trim($header), $matches, PREG_SET_ORDER);

        return self::groupParts($matches, $separators);
    }

    /**
     * Combines an array of arrays into one associative array.
     *
     * Each of the nested arrays should have one or two elements. The first
     * value will be used as the keys in the associative array, and the second
     * will be used as the values, or true if the nested array only contains one
     * element. Array keys are lowercased.
     *
     * Example:
     *
     *     HeaderUtils::combine(array(array("foo", "abc"), array("bar")))
     *     // => array("foo" => "abc", "bar" => true)
     */
    public static function combine(array $parts): array
    {
        $assoc = array();
        foreach ($parts as $part) {
            $name = strtolower($part[0]);
            $value = $part[1] ?? true;
            $assoc[$name] = $value;
        }

        return $assoc;
    }

    /**
     * Joins an associative array into a string for use in an HTTP header.
     *
     * The key and value of each entry are joined with "=", and all entries
     * are joined with the specified separator and an additional space (for
     * readability). Values are quoted if necessary.
     *
     * Example:
     *
     *     HeaderUtils::toString(array("foo" => "abc", "bar" => true, "baz" => "a b c"), ",")
     *     // => 'foo=abc, bar, baz="a b c"'
     */
    public static function toString(array $assoc, string $separator): string
    {
        $parts = array();
        foreach ($assoc as $name => $value) {
            if (true === $value) {
                $parts[] = $name;
            } else {
                $parts[] = $name.'='.self::quote($value);
            }
        }

        return implode($separator.' ', $parts);
    }

    /**
     * Encodes a string as a quoted string, if necessary.
     *
     * If a string contains characters not allowed by the "token" construct in
     * the HTTP specification, it is backslash-escaped and enclosed in quotes
     * to match the "quoted-string" construct.
     */
    public static function quote(string $s): string
    {
        if (preg_match('/^[a-z0-9!#$%&\'*.^_`|~-]+$/i', $s)) {
            return $s;
        }

        return '"'.addcslashes($s, '"\\"').'"';
    }

    /**
     * Decodes a quoted string.
     *
     * If passed an unquoted string that matches the "token" construct (as
     * defined in the HTTP specification), it is passed through verbatimly.
     */
    public static function unquote(string $s): string
    {
        return preg_replace('/\\\\(.)|"/', '$1', $s);
    }

    private static function groupParts(array $matches, string $separators): array
    {
        $separator = $separators[0];
        $partSeparators = substr($separators, 1);

        $i = 0;
        $partMatches = array();
        foreach ($matches as $match) {
            if (isset($match['separator']) && $match['separator'] === $separator) {
                ++$i;
            } else {
                $partMatches[$i][] = $match;
            }
        }

        $parts = array();
        if ($partSeparators) {
            foreach ($partMatches as $matches) {
                $parts[] = self::groupParts($matches, $partSeparators);
            }
        } else {
            foreach ($partMatches as $matches) {
                $parts[] = self::unquote($matches[0][0]);
            }
        }

        return $parts;
    }
}
