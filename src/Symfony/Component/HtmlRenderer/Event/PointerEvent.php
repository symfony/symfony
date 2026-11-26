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

/** @experimental */
final class PointerEvent
{
    public function __construct(public readonly string $type, public readonly float $x, public readonly float $y, public readonly int $button = 0, public readonly bool $shift = false)
    {
        if (!\in_array($type, ['down', 'move', 'up'], true) || !is_finite($x) || !is_finite($y) || $button < 0) {
            throw new \InvalidArgumentException('Pointer input requires a down, move or up type, finite document coordinates and a non-negative button.');
        }
    }
}
