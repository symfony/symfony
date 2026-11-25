<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Form;

/** @internal */
final class NumberStepper
{
    public static function next(float $current, float $step, ?float $min, ?float $max, int $direction, ?float $base = null): ?float
    {
        $base ??= $min ?? 0.0;
        if (!is_finite($current) || !is_finite($step) || $step <= 0 || !is_finite($base) || null !== $min && !is_finite($min) || null !== $max && !is_finite($max)) {
            return null;
        }
        $steps = ($current - $base) / $step;
        if (!is_finite($steps)) {
            return null;
        }
        $steps = abs($steps - round($steps)) < 1.0E-9 ? round($steps) + $direction : (1 === $direction ? floor($steps) + 1 : ceil($steps) - 1);
        $least = null !== $min ? ceil(($min - $base) / $step - 1.0E-9) : -\INF;
        $greatest = null !== $max ? floor(($max - $base) / $step + 1.0E-9) : \INF;
        if ($least > $greatest) {
            return null;
        }
        $next = $base + max($least, min($greatest, $steps)) * $step;
        if (!is_finite($next)) {
            return null;
        }

        return 1 === $direction && $next < $current || -1 === $direction && $next > $current ? $current : $next;
    }

    public static function constrain(float $value, float $min, float $max, float $step): float
    {
        if (!is_finite($value) || !is_finite($min) || !is_finite($max) || !is_finite($step)) {
            throw new \InvalidArgumentException('Range values and bounds must be finite.');
        }
        $max = max($min, $max);
        $value = max($min, min($max, $value));
        if ($step > 0 && is_finite($steps = ($value - $min) / $step) && is_finite($greatest = ($max - $min) / $step)) {
            $snapped = $min + min(round($steps), floor($greatest + 1.0E-9)) * $step;
            if (is_finite($snapped)) {
                $value = $snapped;
            }
        }

        return max($min, min($max, round($value, 10)));
    }

    public static function format(float $value, float $step, ?float $base = null): string
    {
        $decimals = max(self::decimals($step), null !== $base ? self::decimals($base) : 0);
        $text = number_format($value, $decimals, '.', '');
        if ($decimals > 0) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return '-0' === $text ? '0' : $text;
    }

    public static function decimals(float $step): int
    {
        $text = rtrim(\sprintf('%.15F', $step), '0');

        return \strlen(substr($text, strpos($text, '.') + 1));
    }
}
