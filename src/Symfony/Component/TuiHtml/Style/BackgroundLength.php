<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * A size or an offset of a background image: a percentage plus cells, like calc(50% + 2ch).
 *
 * An offset percentage is of the room the image leaves in its positioning area, like CSS background-position, a
 * size one of the area itself.
 *
 * @experimental
 */
final class BackgroundLength
{
    /**
     * @param float $percent Of the positioning area, or of the room left in it for an offset
     * @param int   $cells   Added to it
     * @param bool  $fromEnd Whether an offset is from the right or the bottom edge, like background-position: right 2ch
     */
    public function __construct(public readonly float $percent = 0.0, public readonly int $cells = 0, public readonly bool $fromEnd = false)
    {
    }

    /**
     * @param int $area The columns or the rows of the positioning area
     * @param int $size The columns or the rows of the image, for an offset
     */
    public function resolve(int $area, int $size = 0): int
    {
        $room = $area - $size;
        $offset = (int) round($room * $this->percent / 100) + $this->cells;

        return $this->fromEnd ? $room - $offset : $offset;
    }
}
