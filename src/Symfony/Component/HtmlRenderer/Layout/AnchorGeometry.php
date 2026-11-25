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

/** @internal */
final class AnchorGeometry
{
    /**
     * @param array<'top'|'right'|'bottom'|'left', array{Rect, string}> $edges
     *
     * @return array{float, float}
     */
    public static function room(Rect $anchor, float $viewportWidth, float $viewportHeight, ?array $area, array $edges = [], ?\Closure $half = null): array
    {
        $half ??= static fn (float $value, bool $vertical): float => $value / 2;
        $width = $viewportWidth;
        $height = $viewportHeight;
        if (null !== $area) {
            $height = match ($area[0]) {
                'top' => $anchor->y,
                'bottom' => $viewportHeight - $anchor->y - $anchor->height,
                'span-top' => $anchor->y + $anchor->height,
                'span-bottom' => $viewportHeight - $anchor->y,
                'span-all' => $viewportHeight,
                default => $anchor->height,
            };
            $width = match ($area[1]) {
                'left' => $anchor->x,
                'right' => $viewportWidth - $anchor->x - $anchor->width,
                'span-left' => $anchor->x + $anchor->width,
                'span-right' => $viewportWidth - $anchor->x,
                'span-all' => $viewportWidth,
                default => $anchor->width,
            };
        }
        foreach ($edges as $side => [$edgeAnchor, $edge]) {
            $vertical = \in_array($side, ['top', 'bottom'], true);
            $coordinate = match ($edge) {
                'bottom' => $edgeAnchor->y + $edgeAnchor->height,
                'right' => $edgeAnchor->x + $edgeAnchor->width,
                'center' => $vertical ? $edgeAnchor->y + $half($edgeAnchor->height, true) : $edgeAnchor->x + $half($edgeAnchor->width, false),
                default => $vertical ? $edgeAnchor->y : $edgeAnchor->x,
            };
            match ($side) {
                'top' => $height = $viewportHeight - $coordinate,
                'bottom' => $height = $coordinate,
                'left' => $width = $viewportWidth - $coordinate,
                'right' => $width = $coordinate,
            };
        }

        return [max(0.0, $width), max(0.0, $height)];
    }

    public static function area(Rect $anchor, Rect $containing, array $area): Rect
    {
        $axis = static fn (float $start, float $end, float $before, float $after, string $area): array => match ($area) {
            'left', 'top' => [$start, max($start, min($end, $before))],
            'right', 'bottom' => [max($start, min($end, $after)), $end],
            'center' => [max($start, min($end, $before)), max($start, min($end, $after))],
            'span-left', 'span-top' => [$start, max($start, min($end, $after))],
            'span-right', 'span-bottom' => [max($start, min($end, $before)), $end],
            default => [$start, $end],
        };
        [$left, $right] = $axis($containing->x, $containing->x + $containing->width, $anchor->x, $anchor->x + $anchor->width, $area[1]);
        [$top, $bottom] = $axis($containing->y, $containing->y + $containing->height, $anchor->y, $anchor->y + $anchor->height, $area[0]);

        return new Rect($left, $top, max(0.0, $right - $left), max(0.0, $bottom - $top));
    }

    /**
     * The existing position-area placement, expressed in document units.
     *
     * @param array{string, string} $area
     *
     * @return array{float, float}
     */
    public static function place(Rect $anchor, float $width, float $height, float $viewportWidth, float $viewportHeight, array $area): array
    {
        [$x, $y] = self::nextTo($anchor, $width, $height, $viewportWidth, $viewportHeight, $area);

        return [max(0.0, min($x, $viewportWidth - $width)), max(0.0, min($y, $viewportHeight - $height))];
    }

    /**
     * @param array<'top'|'right'|'bottom'|'left', array{Rect, string}> $edges
     * @param list<string>                                              $flips
     * @param array{bool, bool}                                         $center
     * @param array{float, float}                                       $fallback
     * @param (\Closure(float, bool): float)|null                       $half     Resolves half an extent in device units
     *
     * @return array{float, float}
     */
    public static function nextTo(Rect $anchor, float $width, float $height, float $viewportWidth, float $viewportHeight, ?array $area, array $edges = [], array $flips = [], array $center = [false, false], array $fallback = [0.0, 0.0], ?\Closure $half = null): array
    {
        $half ??= static fn (float $value, bool $vertical): float => $value / 2;
        [$x, $y] = $fallback;
        [$block, $inline] = $area ?? [null, null];
        $y = match ($block) {
            'top' => $anchor->y - $height,
            'bottom' => $anchor->y + $anchor->height,
            'span-top' => $anchor->y + $anchor->height - $height,
            'center', 'span-all' => max(0.0, min($anchor->y + $half($anchor->height - $height, true), $viewportHeight - $height)),
            default => null === $block ? $y : $anchor->y,
        };
        $x = match ($inline) {
            'left' => $anchor->x - $width,
            'right' => $anchor->x + $anchor->width,
            'span-left' => $anchor->x + $anchor->width - $width,
            'center', 'span-all' => max(0.0, min($anchor->x + $half($anchor->width - $width, false), $viewportWidth - $width)),
            default => null === $inline ? $x : $anchor->x,
        };
        foreach ($edges as $side => [$edgeAnchor, $edge]) {
            $coordinate = \in_array($side, ['left', 'right'], true) ? match ($edge) {
                'right' => $edgeAnchor->x + $edgeAnchor->width,
                'center' => $edgeAnchor->x + $half($edgeAnchor->width, false),
                default => $edgeAnchor->x,
            } : match ($edge) {
                'bottom' => $edgeAnchor->y + $edgeAnchor->height,
                'center' => $edgeAnchor->y + $half($edgeAnchor->height, true),
                default => $edgeAnchor->y,
            };
            match ($side) {
                'top' => $y = $coordinate,
                'bottom' => $y = $coordinate - $height,
                'left' => $x = $coordinate,
                'right' => $x = $coordinate - $width,
            };
            $block ??= 'top' === $side ? 'bottom' : ('bottom' === $side ? 'top' : null);
            $inline ??= 'left' === $side ? 'right' : ('right' === $side ? 'left' : null);
        }
        if ($center[0]) {
            $x = $anchor->x + $half($anchor->width - $width, false);
        }
        if ($center[1]) {
            $y = $anchor->y + $half($anchor->height - $height, true);
        }
        if (\in_array('flip-block', $flips, true)) {
            if ('bottom' === $block && $y + $height > $viewportHeight) {
                $y = $anchor->y - $height;
            } elseif ('top' === $block && $y < 0) {
                $y = $anchor->y + $anchor->height;
            }
        }
        if (\in_array('flip-inline', $flips, true)) {
            if ('right' === $inline && $x + $width > $viewportWidth) {
                $x = $anchor->x - $width;
            } elseif ('left' === $inline && $x < 0) {
                $x = $anchor->x + $anchor->width;
            }
        }

        return [$x, $y];
    }
}
