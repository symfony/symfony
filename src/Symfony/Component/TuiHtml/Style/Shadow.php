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
 * A drop shadow below and to the right of a box, like CSS box-shadow.
 *
 * A terminal cannot draw outside the room it gives a widget: the shadow
 * takes columns and rows of it, between the border and the margin.
 *
 * @experimental
 */
final class Shadow
{
    public readonly int $x;
    public readonly int $y;
    public readonly int $blur;
    public readonly int $spread;

    /**
     * @param int        $x      The columns the shadow is offset by, to the right
     * @param int        $y      The rows the shadow is offset by, below
     * @param Color|null $color  The color of the shadow (null = a shade of the terminal background)
     * @param bool       $inset  Whether the shadow is inside the box, along the edges it is offset from, like box-shadow: inset;
     *                           its offsets can then be negative, along the opposite edges
     * @param int        $blur   The cells the edge of the shadow is softened over: its outer ones drawn as half blocks
     * @param int        $spread The cells the shadow is larger than the box by, smaller when negative
     */
    public function __construct(int $x, int $y, public readonly ?Color $color = null, public readonly bool $inset = false, int $blur = 0, int $spread = 0)
    {
        $this->x = max($inset ? -4 : 0, min(4, $x));
        $this->y = max($inset ? -4 : 0, min(4, $y));
        $this->blur = max(0, min(4, $blur));
        $this->spread = max(-4, min(4, $spread));
    }

    /**
     * The columns the shadow takes on the right of the box and the rows below it: its spread counted, and a cell more
     * each way for its blur, which spreads it further.
     *
     * @return array{int, int}
     */
    public function extents(): array
    {
        $blur = $this->blur > 0 ? 1 : 0;

        return [max(0, min(8, $this->x + $this->spread)) + $blur, max(0, min(8, $this->y + $this->spread)) + $blur];
    }
}
