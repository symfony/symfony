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

/** @internal */
final class SizeInterpolation
{
    public static function ends(string $from, string $to, bool $keywords, ?float $content, \Closure $length, string $unit = 'px'): array
    {
        $size = static function (string $value) use ($content, $keywords, $length, $unit): ?float {
            $value = strtolower(trim($value));
            $intrinsic = ['auto', 'fit-content', 'min-content', 'max-content', 'stretch'];
            if (\in_array($value, $intrinsic, true)) {
                return $keywords ? $content : null;
            }
            if (null !== $match = self::calculation($value)) {
                $basis = trim($match[1]);
                $size = \in_array($basis, [...$intrinsic, 'any'], true) ? $content : $length($basis);
                if (null === $size) {
                    return null;
                }
                $expression = trim($match[2] ?? '');

                return '' === $expression ? $size : $length('calc('.preg_replace('/(?<![\\w-])size(?![\\w-])/', $size.$unit, $expression).')');
            }

            return $length($value);
        };
        if (!self::intrinsic($from) && !self::intrinsic($to)) {
            return [$from, $to];
        }
        $start = $size($from);
        $end = $size($to);

        return null !== $start && null !== $end && is_finite($start) && is_finite($end) ? [$start.$unit, $end.$unit] : [$from, $to];
    }

    public static function calculation(string $value): ?array
    {
        return preg_match('/^calc-size\(\s*([^,()]+(?:\([^()]*\))?)\s*(?:,(.*))?\)$/s', strtolower(trim($value)), $match) ? $match : null;
    }

    public static function intrinsic(string $value): bool
    {
        return (bool) preg_match('/^(?:auto|fit-content|min-content|max-content|stretch|calc-size\(.*\))$/is', trim($value));
    }
}
