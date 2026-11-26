<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Geometry;

final class Rect
{
    public function __construct(public readonly float $x, public readonly float $y, public readonly float $width, public readonly float $height)
    {
        if (!is_finite($x) || !is_finite($y) || !is_finite($width) || !is_finite($height) || $width < 0 || $height < 0) {
            throw new \InvalidArgumentException('A rectangle requires finite coordinates and non-negative dimensions.');
        }
    }

    public function contains(float $x, float $y): bool
    {
        return $x >= $this->x && $y >= $this->y && $x < $this->x + $this->width && $y < $this->y + $this->height;
    }
}
