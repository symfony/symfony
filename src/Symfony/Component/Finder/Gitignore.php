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

            $line = preg_replace('~(?<!\\\\) +$~', '', $line);

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

        $regex = preg_quote(str_replace('\\', '', $gitignoreLine), '~');
        $regex = preg_replace_callback('~\\\\\[((?:\\\\!)?)([^\[\]]*)\\\\\]~', static fn (array $matches): string => '['.('' !== $matches[1] ? '^' : '').str_replace('\\-', '-', $matches[2]).']', $regex);
        $regex = preg_replace('~(?:(?:\\\\\*){2,}(/?))+~', '(?:(?:(?!//).(?<!//))+$1)?', $regex);
        $regex = preg_replace('~\\\\\*~', '[^/]*', $regex);
        $regex = preg_replace('~\\\\\?~', '[^/]', $regex);

        return ($isAbsolute ? '' : '(?:[^/]+/)*')
            .$regex
            .(!str_ends_with($gitignoreLine, '/') ? '(?:$|/)' : '');
    }
}
