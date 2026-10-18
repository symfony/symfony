<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

/**
 * Gitignore matches against text.
 *
 * @author Michael Voříšek <vorismi3@fel.cvut.cz>
 * @author Ahmed Abdou <mail@ahmd.io>
 */
class Gitignore
{
    /**
     * Returns a regexp which is the equivalent of the gitignore pattern.
     *
     * Format specification: https://git-scm.com/docs/gitignore#_pattern_format
     */
    public static function toRegex(string $gitignoreFileContent): string
    {
        return self::buildRegex($gitignoreFileContent, false);
    }

    public static function toRegexMatchingNegatedPatterns(string $gitignoreFileContent): string
    {
        return self::buildRegex($gitignoreFileContent, true);
    }

    private static function buildRegex(string $gitignoreFileContent, bool $inverted): string
    {
        $gitignoreLines = preg_split('~\r\n?|\n~', $gitignoreFileContent);

        $res = self::lineToRegex('');
        $alternatives = [];
        foreach ($gitignoreLines as $line) {
            // only a line starting with "#" is a comment, and only trailing spaces are stripped
            if (str_starts_with($line, '#')) {
                continue;
            }

            $line = preg_replace('~(?<!\\\\)((?:\\\\\\\\)*+) +$~', '$1', $line);

            if (str_starts_with($line, '!')) {
                $line = substr($line, 1);
                $isNegative = true;
            } else {
                $isNegative = false;
            }

            if ('' !== $line) {
                if ($isNegative xor $inverted) {
                    // a negative pattern only cancels the patterns before it, so it opens a new nesting level
                    $res = '(?!'.self::lineToRegex($line).'$)'.self::alternate($res, $alternatives);
                } else {
                    $alternatives[] = self::lineToRegex($line);
                }
            }
        }

        return '~^(?:'.self::alternate($res, $alternatives).')~s';
    }

    /**
     * Consecutive patterns share a single group, so deep nesting stays proportional to the number of negative patterns.
     *
     * @param list<string> $alternatives
     */
    private static function alternate(string $res, array &$alternatives): string
    {
        if (!$alternatives) {
            return $res;
        }

        $regex = '(?:'.$res.'|'.implode('|', $alternatives).')';
        $alternatives = [];

        return $regex;
    }

    private static function lineToRegex(string $gitignoreLine): string
    {
        if ('' === $gitignoreLine) {
            return '$f'; // always false
        }

        $slashPos = strpos($gitignoreLine, '/');
        if (false !== $slashPos && \strlen($gitignoreLine) - 1 !== $slashPos) {
            if (0 === $slashPos) {
                $gitignoreLine = substr($gitignoreLine, 1);
            }
            $isAbsolute = true;
        } else {
            $isAbsolute = false;
        }

        $parts = preg_split('~\\\\(.)~s', $gitignoreLine, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if (str_ends_with(end($parts), '\\')) {
            return '$f'; // a trailing backslash never matches
        }

        $regex = '';
        foreach ($parts as $i => $part) {
            // escaped characters are written in hex so that the replacements below leave them alone
            $regex .= $i % 2 ? \sprintf('\x%02X', \ord($part)) : preg_quote($part, '~');
        }
        $regex = preg_replace_callback('~\\\\\[((?:\\\\[!^])?+)((?:\\\\\])?+[^\]]*)\\\\\]~', static fn (array $matches): string => ('' !== $matches[1] ? '(?!/)[^' : '[').self::rangesToRegex($matches[2]).']', $regex);
        $regex = preg_replace('~(?:(?:\\\\\*){2,}(/?))+~', '(?:(?:(?!//).(?<!//))+$1)?', $regex);
        $regex = preg_replace('~\\\\\*~', '[^/]*', $regex);
        $regex = preg_replace('~\\\\\?~', '[^/]', $regex);

        return ($isAbsolute ? '' : '(?:[^/]+/)*')
            .$regex
            .(!str_ends_with($gitignoreLine, '/') ? '(?:$|/)' : '');
    }

    /**
     * Unescapes the "-" of each range. A range that ends before its start only matches its start, as in git.
     */
    private static function rangesToRegex(string $class): string
    {
        return preg_replace_callback('~(\\\\x[0-9A-F]{2}|\\\\?.)\\\\-(\\\\x[0-9A-F]{2}|\\\\?.)~', static function (array $m): string {
            [$from, $to] = array_map(static fn (string $c): int => 4 === \strlen($c) ? hexdec(substr($c, 2)) : \ord($c[-1]), [$m[1], $m[2]]);

            return $from <= $to ? $m[1].'-'.$m[2] : $m[1];
        }, $class) ?? $class;
    }
}
