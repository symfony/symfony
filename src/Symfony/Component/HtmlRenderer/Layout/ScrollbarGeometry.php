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
final class ScrollbarGeometry
{
    /** @return array<string, Rect> Painted tracks and the non-activating corner between two bars. */
    public static function regions(Fragment $fragment): array
    {
        if (null === $scroll = $fragment->scroll) {
            return [];
        }
        $port = $scroll->port;
        $rtl = 'rtl' === ($fragment->style['direction'] ?? 'ltr');
        $regions = [];
        if ($scroll->horizontalBar && $port->width > 0 && $scroll->reserved[2] > 0) {
            $regions['x'] = new Rect($port->x, $port->y + $port->height, $port->width, $scroll->reserved[2]);
        }
        if ($scroll->verticalBar && $port->height > 0 && $scroll->reserved[$rtl ? 3 : 1] > 0) {
            $regions['y'] = new Rect($rtl ? $fragment->getPaddingBox()->x : $port->x + $port->width, $port->y, $scroll->reserved[$rtl ? 3 : 1], $port->height);
        }
        if (isset($regions['x'], $regions['y'])) {
            $regions['corner'] = new Rect($regions['y']->x, $regions['x']->y, $regions['y']->width, $regions['x']->height);
        }

        return $regions;
    }

    public static function thumbLength(float $track, float $viewport, float $content): float
    {
        if ($content <= $viewport) {
            return max(0.0, $track);
        }

        return max(0.0, min($track, $track * $viewport / $content));
    }

    public static function thumbOffset(float $track, float $thumb, float $viewport, float $content, float $offset): float
    {
        $maximum = $content - $viewport;
        if ($maximum <= 0) {
            return 0.0;
        }

        return max(0.0, $track - $thumb) * max(0.0, min($maximum, $offset)) / $maximum;
    }
}
