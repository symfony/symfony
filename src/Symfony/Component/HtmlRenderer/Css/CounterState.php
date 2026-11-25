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

/** @internal */
final class CounterState
{
    public static function apply(array &$counters, int $depth, array $declarations): void
    {
        foreach (['counter-reset' => 0, 'counter-set' => 0, 'counter-increment' => 1] as $property => $default) {
            if (!isset($declarations[$property])) {
                continue;
            }
            foreach (self::parse($declarations[$property], $default) as $name => $value) {
                $top = isset($counters[$name]) ? array_key_last($counters[$name]) : null;
                if ('counter-reset' === $property) {
                    // A counter reset by a previous sibling is replaced, not nested
                    if (null !== $top && $counters[$name][$top][1] === $depth) {
                        $counters[$name][$top][0] = $value;
                    } else {
                        $counters[$name][] = [$value, $depth];
                    }
                    continue;
                }
                if (null === $top) {
                    // Counting a counter that is not in scope creates it on the element
                    $counters[$name] = [[0, $depth]];
                    $top = 0;
                }
                $counters[$name][$top][0] = self::clamp('counter-set' === $property ? $value : $counters[$name][$top][0] + $value);
            }
        }
    }

    public static function parse(string $value, int $default): array
    {
        $parts = ValueTokenizer::split($value);
        if (['none'] === $parts) {
            return [];
        }
        $counters = [];
        foreach ($parts as $i => $part) {
            if (preg_match('/^[+-]?\d+$/', $part)) {
                continue;
            }
            if (!preg_match('/^-?[a-z_][\w-]*$/i', $part) || \in_array(strtolower($part), ['none', 'inherit', 'initial', 'unset'], true)) {
                // An invalid declaration is ignored as a whole
                return [];
            }
            $next = $parts[$i + 1] ?? null;
            $counters[$part] = null !== $next && preg_match('/^[+-]?\d+$/', $next) ? self::clamp((int) $next) : $default;
        }

        return $counters;
    }

    public static function clamp(int|float $value): int
    {
        return (int) max(-2147483648, min(2147483647, $value));
    }

    public static function leave(array &$counters, int $depth): void
    {
        foreach ($counters as $name => $instances) {
            while ($instances && $instances[array_key_last($instances)][1] === $depth) {
                array_pop($instances);
            }
            if ($instances) {
                $counters[$name] = $instances;
            } else {
                unset($counters[$name]);
            }
        }
    }

    public static function text(array $counters, string $function, string $name, ?string $separator, ?string $style, CounterStyles $styles): string
    {
        $instances = $counters[$name] ?? [];
        // A counter not in scope is 0
        $values = $instances ? array_map(static fn (array $instance) => $instance[0], $instances) : [0];
        $style ??= 'decimal';
        if ('counter' === $function) {
            return 'none' === strtolower($style) ? '' : $styles->format($values[array_key_last($values)], $style);
        }
        $glue = null !== $separator ? GeneratedContent::unescape(substr($separator, 1, -1)) : '';

        return implode($glue, array_map(static fn (int $value) => 'none' === strtolower($style) ? '' : $styles->format($value, $style), $values));
    }
}
