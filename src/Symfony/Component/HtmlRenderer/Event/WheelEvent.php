<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Event;

/**
 * Coordinates and deltas use document layout units.
 *
 * @experimental
 */
final readonly class WheelEvent
{
    public function __construct(public float $x, public float $y, public float $deltaX, public float $deltaY)
    {
        if (!is_finite($x) || !is_finite($y) || !is_finite($deltaX) || !is_finite($deltaY)) {
            throw new \InvalidArgumentException('Wheel coordinates and deltas must be finite.');
        }
    }
}
