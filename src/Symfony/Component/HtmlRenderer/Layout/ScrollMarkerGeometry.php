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

/** @internal */
final class ScrollMarkerGeometry
{
    /**
     * Selects the last marker starting before the scroll offset, or the last
     * visible marker when the viewport reaches the end of its content.
     *
     * @param list<float|int|null> $starts Positions relative to the scroll content; null for unavailable geometry
     */
    public static function current(array $starts, float $offset, float $content, float $viewport): ?int
    {
        if (!$starts) {
            return null;
        }
        $current = 0;
        $atEnd = $content > $viewport && $offset + $viewport >= $content;
        foreach ($starts as $index => $start) {
            if (null !== $start && ($start <= $offset || $atEnd && $start < $offset + $viewport)) {
                $current = $index;
            }
        }

        return $current;
    }
}
