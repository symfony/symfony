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

use Symfony\Component\HtmlRenderer\Geometry\Rect;

/** @internal Outline rings do not contribute to layout or scrolling extents. */
final class OutlineGeometry
{
    public static function outer(Rect $borderBox, float $widthX, float $widthY, float $offsetX, float $offsetY): ?Rect
    {
        $distanceX = $widthX + $offsetX;
        $distanceY = $widthY + $offsetY;
        $width = $borderBox->width + 2 * $distanceX;
        $height = $borderBox->height + 2 * $distanceY;

        return $width > 0 && $height > 0 ? new Rect($borderBox->x - $distanceX, $borderBox->y - $distanceY, $width, $height) : null;
    }
}
