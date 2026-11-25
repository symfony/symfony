<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

use Symfony\Component\HtmlRenderer\Css\ColorSpace;
use Symfony\Component\HtmlRenderer\Css\ColorValue;

/** @internal */
final class GradientGeometry
{
    public static function resolve(array $stops, float $length): array
    {
        $at = array_map(static fn (array $stop) => null === $stop[1] ? null : $stop[1] + ($length > 0 ? $stop[2] / $length : 0.0), $stops);
        $count = \count($at);
        if (0 === $count) {
            return [];
        }
        $at[0] ??= 0.0;
        $at[$count - 1] ??= 1.0;
        for ($i = 1; $i < $count; ++$i) {
            if (null === $at[$i]) {
                for ($next = $i; null === $at[$next]; ++$next) {
                }
                $from = (float) $at[$i - 1];
                for ($j = $i; $j < $next; ++$j) {
                    $at[$j] = $from + ((float) $at[$next] - $from) * ($j - $i + 1) / ($next - $i + 1);
                }
            }
            $at[$i] = max((float) $at[$i - 1], (float) $at[$i]);
        }

        return array_map(static fn (array $stop, ?float $position) => isset($stop[3]) && $stop[3] < 1.0 ? [$stop[0], (float) $position, $stop[3]] : [$stop[0], (float) $position], $stops, $at);
    }

    public static function direction(float $angle): array
    {
        $angle = fmod(fmod($angle, 360.0) + 360.0, 360.0);

        return match ($angle) {
            0.0 => [0.0, -1.0],
            90.0 => [1.0, 0.0],
            180.0 => [0.0, 1.0],
            270.0 => [-1.0, 0.0],
            default => [sin(deg2rad($angle)), -cos(deg2rad($angle))],
        };
    }

    public static function radii(float $cx, float $cy, float $width, float $height, bool $circle, string|array $extent, float $minimum = 0.0): array
    {
        if (\is_array($extent)) {
            $rx = (float) $extent[0];
            $ry = null !== $extent[1] ? (float) $extent[1] : $rx;

            return [max($minimum, $rx), max($minimum, $ry)];
        }
        // The distances to the sides of the box, the closest or the farthest of each axis
        $pick = str_starts_with($extent, 'closest') ? min(...) : max(...);
        [$sx, $sy] = [$pick($cx, $width - $cx), $pick($cy, $height - $cy)];
        if ($circle) {
            $r = match ($extent) {
                'closest-side' => min($sx, $sy),
                'farthest-side' => max($sx, $sy),
                default => hypot($sx, $sy),
            };

            return [max($minimum, $r), max($minimum, $r)];
        }
        // An ellipse through a corner keeps the proportions of the one touching the sides
        $factor = str_ends_with($extent, 'corner') ? \M_SQRT2 : 1.0;

        return [max($minimum, $sx * $factor), max($minimum, $sy * $factor)];
    }

    public static function lineLength(float $width, float $height, array $direction): float
    {
        return abs($width * $direction[0]) + abs($height * $direction[1]);
    }

    public static function fraction(string $kind, float $x, float $y, float $a, float $b, float $length, float $from = 0.0): float
    {
        if ('radial' === $kind) {
            return $a > 0 && $b > 0 ? sqrt(($x / $a) ** 2 + ($y / $b) ** 2) : (0.0 === $x && 0.0 === $y ? 0.0 : \INF);
        }
        if ('conic' === $kind) {
            $degrees = rad2deg(atan2($x, -$y)) - $from;

            return fmod(fmod($degrees, 360.0) + 360.0, 360.0) / 360;
        }

        return $length > 0 ? ($x * $a + $y * $b) / $length + 0.5 : 0.0;
    }

    public static function resolveModel(array $gradient, float $width, float $height, ?\Closure $resolveColor = null): ?array
    {
        $resolveColor ??= ColorValue::parse(...);
        $center = $gradient['center'];
        $cx = null !== $center ? self::offset($center[0], $width) : $width / 2;
        $cy = null !== $center ? self::offset($center[1], $height) : $height / 2;
        $direction = self::direction($gradient['angle']);
        $extent = $gradient['extent'];
        if (\is_array($extent)) {
            $extent = [self::offset($extent[0], $width), null !== $extent[1] ? self::offset($extent[1], $height) : null];
        }
        $radii = 'radial' === $gradient['kind'] ? self::radii($cx, $cy, $width, $height, $gradient['circle'], $extent) : [0.0, 0.0];
        $length = match ($gradient['kind']) {
            'radial' => $radii[0],
            'conic' => 0.0,
            default => self::lineLength($width, $height, $direction),
        };
        if (array_any([$cx, $cy, ...$radii, $length], static fn (float $number) => !is_finite($number))) {
            return null;
        }
        $raw = [];
        foreach ($gradient['stops'] as [$color, $share, $offset]) {
            if (!\is_array($rgba = $resolveColor($color))) {
                return null;
            }
            $raw[] = [$rgba, $share, $offset];
        }

        return ['kind' => $gradient['kind'], 'repeating' => $gradient['repeating'], 'from' => $gradient['from'], 'center' => [$cx, $cy], 'direction' => $direction, 'radii' => $radii, 'length' => $length, 'stops' => self::resolve($raw, $length), 'space' => $gradient['space'], 'hue' => $gradient['hue']];
    }

    public static function sample(array $model, float $x, float $y): array
    {
        [$cx, $cy] = $model['center'];
        [$a, $b] = 'radial' === $model['kind'] ? $model['radii'] : $model['direction'];
        $t = self::fraction($model['kind'], $x - $cx, $y - $cy, $a, $b, $model['length'], $model['from']);
        $stops = $model['stops'];
        $first = $stops[0];
        $last = $stops[\count($stops) - 1];
        if ($model['repeating'] && $last[1] > $first[1]) {
            $span = $last[1] - $first[1];
            $t = $first[1] + fmod(fmod($t - $first[1], $span) + $span, $span);
        }
        if ($t <= $first[1]) {
            return $first[0];
        }
        foreach ($stops as $index => $stop) {
            if ($t <= $stop[1]) {
                $before = $stops[$index - 1];
                $span = $stop[1] - $before[1];

                return $span > 0 ? ColorSpace::interpolate($model['space'], $model['hue'], $before[0], $stop[0], ($t - $before[1]) / $span) : $stop[0];
            }
        }

        return $last[0];
    }

    private static function offset(array $position, float $size): float
    {
        $offset = $size * $position['percent'] / 100 + $position['offset'];

        return $position['fromEnd'] ? $size - $offset : $offset;
    }
}
