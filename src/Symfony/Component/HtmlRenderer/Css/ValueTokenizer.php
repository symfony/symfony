<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/** @internal */
final class ValueTokenizer
{
    public static function split(string $value): array
    {
        // Most values hold no function nor string: whitespace alone splits them
        if (false === strpbrk($value, '("\'')) {
            return preg_split('/\s+/', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $parts = [];
        $current = '';
        $depth = 0;
        $length = \strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ('"' === $char || "'" === $char) {
                // A string, its escaped quotes included
                $end = $i + 1;
                while ($end < $length && $value[$end] !== $char) {
                    $end += '\\' === $value[$end] ? 2 : 1;
                }
                $current .= substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            }
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (0 === $depth && ctype_space($char)) {
                if ('' !== $current) {
                    $parts[] = $current;
                    $current = '';
                }
                continue;
            }
            $current .= $char;
        }
        if ('' !== $current) {
            $parts[] = $current;
        }

        return $parts;
    }

    public static function findOutsideStrings(string $value, string $pattern, int $offset = 0): ?int
    {
        while (preg_match($pattern, $value, $m, \PREG_OFFSET_CAPTURE, $offset)) {
            $at = $m[0][1];
            // In a string when an odd count of unescaped quotes of a kind opens before it
            $quote = null;
            for ($i = 0; $i < $at; ++$i) {
                $char = $value[$i];
                if ('\\' === $char) {
                    ++$i;
                } elseif (null === $quote && ('"' === $char || "'" === $char)) {
                    $quote = $char;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            }
            if (null === $quote) {
                return $at;
            }
            $offset = $at + 1;
        }

        return null;
    }

    public static function closingParenthesis(string $value, int $open): int
    {
        $depth = 0;
        $quote = null;
        $length = \strlen($value);
        for ($i = $open; $i < $length; ++$i) {
            $char = $value[$i];
            if ('\\' === $char) {
                ++$i;
            } elseif (null !== $quote) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif ('(' === $char) {
                ++$depth;
            } elseif (')' === $char && 0 === --$depth) {
                return $i;
            }
        }
        throw new InvalidArgumentException(\sprintf('Missing ")" in "%s".', $value));
    }
}
