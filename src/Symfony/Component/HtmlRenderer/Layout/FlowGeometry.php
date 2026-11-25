<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Geometry\Rect;

/**
 * @internal
 */
final class FlowGeometry
{
    /**
     * @param list<array{side: string, bounds: Rect}> $floats
     *
     * @return array{float, float}
     */
    public static function interval(array $floats, float $x, float $y, float $width, float $height): array
    {
        $left = $x;
        $right = $x + $width;
        foreach ($floats as $float) {
            $bounds = $float['bounds'];
            if ($bounds->y >= $y + $height || $bounds->y + $bounds->height <= $y) {
                continue;
            }
            if ('left' === $float['side']) {
                $left = max($left, $bounds->x + $bounds->width);
            } else {
                $right = min($right, $bounds->x);
            }
        }

        return [$left, max(0.0, $right - $left)];
    }

    public static function clear(array $floats, float $y, string $side): float
    {
        if (!\in_array($side, ['left', 'right', 'both'], true)) {
            return $y;
        }
        foreach ($floats as $float) {
            if ('both' === $side || $side === $float['side']) {
                $y = max($y, $float['bounds']->y + $float['bounds']->height);
            }
        }

        return $y;
    }

    public static function nextEnd(array $floats, float $y): ?float
    {
        $next = null;
        foreach ($floats as $float) {
            $bottom = $float['bounds']->y + $float['bounds']->height;
            if ($float['bounds']->y <= $y && $bottom > $y) {
                $next = null === $next ? $bottom : min($next, $bottom);
            }
        }

        return $next;
    }
}
