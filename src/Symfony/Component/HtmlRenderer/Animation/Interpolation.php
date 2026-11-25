<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Animation;

use Symfony\Component\HtmlRenderer\Css\ColorSpace;
use Symfony\Component\HtmlRenderer\Css\ColorValue;

/**
 * Interpolates numeric component values and delegates color interpretation when supplied.
 *
 * @internal
 */
final class Interpolation
{
    private const LENGTH_UNITS = ['%', 'px', 'ch', 'lh', 'rlh', 'em', 'rem', 'ex', 'ic', 'cap', 'in', 'cm', 'mm', 'q', 'pt', 'pc', 'vw', 'vh', 'vi', 'vb', 'vmin', 'vmax', 'svw', 'svh', 'lvw', 'lvh', 'dvw', 'dvh', 'cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax'];
    private const COLOR_FUNCTIONS = ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color', 'color-mix', 'light-dark'];

    public static function between(string $from, string $to, float $t): string
    {
        if (0.0 === $t || $from === $to) {
            return $from;
        }
        if (1.0 === $t) {
            return $to;
        }
        if (\is_array($a = ColorValue::parse($from)) && \is_array($b = ColorValue::parse($to))) {
            return ColorValue::stringify(ColorSpace::interpolate('srgb', 'shorter', $a, $b, max(0.0, min(1.0, $t))));
        }
        if (\in_array([strtolower(trim($from)), strtolower(trim($to))], [['visible', 'hidden'], ['hidden', 'visible'], ['visible', 'collapse'], ['collapse', 'visible']], true)) {
            return $t > 0.0 && $t < 1.0 ? 'visible' : ($t <= 0.0 ? $from : $to);
        }

        return self::combine($from, $to, static fn (float $a, float $b, string $unit): string => self::format($a + ($b - $a) * $t).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' * '.self::format(1 - $t).' + '.self::format($b).$unitB.' * '.self::format($t).')', static fn (array $a, array $b): string => ColorValue::stringify(ColorSpace::interpolate('srgb', 'shorter', $a, $b, max(0.0, min(1.0, $t)))), static fn (string $value): ?array => \is_array($color = ColorValue::parse($value)) ? $color : null) ?? ($t < 0.5 ? $from : $to);
    }

    public static function add(string $underlying, string $value): string
    {
        return self::combine($underlying, $value, static fn (float $a, float $b, string $unit): string => self::format($a + $b).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' + '.self::format($b).$unitB.')') ?? $value;
    }

    public static function isInterpolable(string $from, string $to): bool
    {
        $half = self::between($from, $to, 0.5);

        return $half !== $from && $half !== $to;
    }

    public static function combine(string $from, string $to, \Closure $numbers, ?\Closure $lengths, ?\Closure $colors = null, ?\Closure $resolveColor = null): ?string
    {
        if (null === ($a = self::parts($from, $resolveColor)) || null === ($b = self::parts($to, $resolveColor)) || \count($a) !== \count($b) || \count($a) > 256) {
            return null;
        }
        $value = '';
        foreach ($a as $i => [$type, $x, $unitX]) {
            [$otherType, $y, $unitY] = $b[$i];
            if ($type !== $otherType) {
                return null;
            }
            if ('color' === $type) {
                // Preserve an unchanged named color
                $value .= $unitX === $unitY ? $unitX : $colors($x, $y);
                continue;
            }
            if ('other' === $type) {
                if ($x !== $y) {
                    return null;
                }
                $value .= $x;
                continue;
            }
            // A 0 without a unit is a length of the other's unit
            if ($unitX !== $unitY && 0.0 === $x && '' === $unitX && \in_array($unitY, self::LENGTH_UNITS, true)) {
                $unitX = $unitY;
            } elseif ($unitX !== $unitY && 0.0 === $y && '' === $unitY && \in_array($unitX, self::LENGTH_UNITS, true)) {
                $unitY = $unitX;
            }
            if ($unitX === $unitY) {
                $value .= $numbers($x, $y, $unitX);
            } elseif (null !== $lengths && \in_array($unitX, self::LENGTH_UNITS, true) && \in_array($unitY, self::LENGTH_UNITS, true)) {
                $value .= $lengths($x, $unitX, $y, $unitY);
            } else {
                return null;
            }
        }

        return $value;
    }

    private static function parts(string $value, ?\Closure $resolveColor): ?array
    {
        $value = trim($value);
        $length = \strlen($value);
        $parts = [];
        $offset = 0;
        while ($offset < $length) {
            if (!preg_match('/\G(?:(?<space>\s+)|(?<string>"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')|(?<url>url\([^)]*\))|(?<number>[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(?<unit>%|[a-z]+)?|(?<hex>#[0-9a-f]+)|(?<function>-?[a-z_][\w-]*)\(|(?<word>-?[a-z_][\w-]*)|(?<char>.))/Ais', $value, $m, 0, $offset)) {
                return null;
            }
            $offset += \strlen($m[0]);
            if ('' !== ($m['space'] ?? '')) {
                $parts[] = ['other', ' ', ''];
            } elseif ('' !== ($m['number'] ?? '')) {
                $parts[] = ['number', (float) $m['number'], strtolower($m['unit'] ?? '')];
            } elseif ('' !== ($m['function'] ?? '')) {
                $name = strtolower($m['function']);
                if ('var' === $name || 'env' === $name || 'attr' === $name) {
                    return null;
                }
                if (null === $resolveColor || !\in_array($name, self::COLOR_FUNCTIONS, true)) {
                    $parts[] = ['other', $name.'(', ''];
                    continue;
                }
                // The whole color function, its parentheses balanced
                $start = $offset - \strlen($m[0]);
                for ($depth = 1; $offset < $length && $depth > 0; ++$offset) {
                    $depth += match ($value[$offset]) {
                        '(' => 1,
                        ')' => -1,
                        default => 0,
                    };
                }
                if (null === ($color = $resolveColor(substr($value, $start, $offset - $start)))) {
                    return null;
                }
                $parts[] = ['color', $color, strtolower(substr($value, $start, $offset - $start))];
            } elseif ('' !== ($word = $m['hex'] ?? '') || '' !== $word = $m['word'] ?? '') {
                $parts[] = null !== ($color = null !== $resolveColor ? $resolveColor($word) : null) ? ['color', $color, strtolower($word)] : ['other', strtolower($word), ''];
            } else {
                $parts[] = ['other', $m[0], ''];
            }
        }

        return $parts;
    }

    public static function format(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }
}
