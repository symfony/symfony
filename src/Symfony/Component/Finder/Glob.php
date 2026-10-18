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
 * Glob matches globbing patterns against text.
 *
 *     if match_glob("foo.*", "foo.bar") echo "matched\n";
 *
 *     // prints foo.bar and foo.baz
 *     $regex = glob_to_regex("foo.*");
 *     for (['foo.bar', 'foo.baz', 'foo', 'bar'] as $t)
 *     {
 *         if (/$regex/) echo "matched: $car\n";
 *     }
 *
 * Glob implements glob(3) style matching that can be used to match
 * against text, rather than fetching names from a filesystem.
 *
 * Based on the Perl Text::Glob module.
 *
 * @author Fabien Potencier <fabien@symfony.com> PHP port
 * @author     Richard Clamp <richardc@unixbeard.net> Perl version
 * @copyright  2004-2005 Fabien Potencier <fabien@symfony.com>
 * @copyright  2002 Richard Clamp <richardc@unixbeard.net>
 */
class Glob
{
    /**
     * Returns a regexp which is the equivalent of the glob pattern.
     */
    public static function toRegex(string $glob, bool $strictLeadingDot = true, bool $strictWildcardSlash = true, string $delimiter = '#'): string
    {
        $firstByte = true;
        $escaping = false;
        $curlies = [];
        $bracketScanEnd = 0;
        $regex = '';
        if ($unanchored = str_starts_with($glob, '**/')) {
            $glob = '/'.$glob;
        }
        $sizeGlob = \strlen($glob);
        for ($i = 0; $i < $sizeGlob; ++$i) {
            $car = $glob[$i];
            if ($firstByte && $strictLeadingDot && '.' !== $car) {
                $regex .= '(?=[^\.])';
            }

            $firstByte = '/' === $car;

            if ($firstByte && $strictWildcardSlash && isset($glob[$i + 2]) && '**' === $glob[$i + 1].$glob[$i + 2] && (!isset($glob[$i + 3]) || '/' === $glob[$i + 3])) {
                $car = '[^/]++/';
                if (!isset($glob[$i + 3])) {
                    $car .= '?';
                }

                if ($strictLeadingDot) {
                    $car = '(?=[^\.])'.$car;
                }

                $car = '/(?:'.$car.')*';
                $i += 2 + isset($glob[$i + 3]);

                if ('/' === $delimiter) {
                    $car = str_replace('/', '\\/', $car);
                }
            }

            if ($delimiter === $car || '.' === $car || '(' === $car || ')' === $car || '|' === $car || '+' === $car || '^' === $car || '$' === $car) {
                $regex .= "\\$car";
            } elseif ('[' === $car && !$escaping && null !== $class = self::bracketExpressionToRegex($glob, $i, $delimiter, $bracketScanEnd)) {
                $regex .= $class;
            } elseif ('*' === $car) {
                if ($escaping || $strictWildcardSlash || !str_ends_with($regex, '.*')) {
                    // ".*.*" matches the same as ".*" but backtracks quadratically
                    $regex .= $escaping ? '\\*' : ($strictWildcardSlash ? '[^/]*' : '.*');
                }
            } elseif ('?' === $car) {
                $regex .= $escaping ? '\\?' : ($strictWildcardSlash ? '[^/]' : '.');
            } elseif ('{' === $car) {
                if (!$escaping) {
                    $curlies[] = [\strlen($regex)];
                }
                $regex .= $escaping ? '\\{' : '(';
            } elseif ('}' === $car && $curlies) {
                $regex .= $escaping ? '}' : ')';
                if (!$escaping) {
                    array_pop($curlies);
                }
            } elseif (',' === $car && $curlies) {
                if (!$escaping) {
                    $curlies[\count($curlies) - 1][] = \strlen($regex);
                }
                $regex .= $escaping ? ',' : '|';
            } elseif ('\\' === $car) {
                if ($escaping) {
                    $regex .= '\\\\';
                    $escaping = false;
                } else {
                    $escaping = true;
                }

                continue;
            } elseif ($escaping && ('[' === $car || ']' === $car)) {
                $regex .= '\\'.$car;
            } else {
                $regex .= $car;
            }
            $escaping = false;
        }

        // A "{" that no "}" closes is a literal character, and so are the "," it contains
        $prefix = '';
        $start = 0;
        foreach (array_merge(...$curlies) as $offset) {
            $prefix .= substr($regex, $start, $offset - $start).('(' === $regex[$offset] ? '\\{' : ',');
            $start = $offset + 1;
        }
        $regex = $prefix.substr($regex, $start);

        if ($unanchored) {
            $regex = substr_replace($regex, '?', 1 + ('/' === $delimiter) + ($strictLeadingDot ? \strlen('(?=[^\.])') : 0), 0);
        }

        return $delimiter.'^'.$regex.'$'.$delimiter;
    }

    /**
     * Returns null when a "/" comes before the closing "]", and "\[" when no "]" closes the "[" at position $i.
     *
     * $scanEnd is where the last scan stopped without finding a "]": every "[" before that position gets the same result.
     */
    private static function bracketExpressionToRegex(string $glob, int &$i, string $delimiter, int &$scanEnd): ?string
    {
        $sizeGlob = \strlen($glob);

        if ($i < $scanEnd) {
            return $scanEnd < $sizeGlob ? null : '\\[';
        }

        $class = '[';

        for ($j = $i + 1; $j < $sizeGlob; ++$j) {
            $car = $glob[$j];

            if (']' === $car && $j > $i + 1) {
                $i = $j;

                return $class.']';
            }

            if ('/' === $car || ('\\' === $car && '/' === ($glob[$j + 1] ?? ''))) {
                $scanEnd = $j;

                return null;
            }

            if ('[' === $car && preg_match('/\[:[a-z]++:\]/A', $glob, $m, 0, $j)) {
                $class .= $m[0];
                $j += \strlen($m[0]) - 1;
            } elseif ('\\' === $car) {
                if (!isset($glob[++$j])) {
                    break;
                }
                $class .= preg_quote($glob[$j], $delimiter);
            } elseif ($delimiter === $car || str_contains('.()|+^$', $car)) {
                $class .= '\\'.$car;
            } else {
                $class .= $car;
            }
        }
        $scanEnd = $sizeGlob;

        return '\\[';
    }
}
