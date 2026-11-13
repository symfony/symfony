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
 * A size that is the smallest or the largest of sizes mixing a percentage of a room and cells, like max(1ch, 5%):
 * clamp(a, b, c) is the largest of a and the smallest of b and c.
 *
 * @experimental
 */
final class PercentLength
{
    /**
     * @param bool                           $largest Whether the size is the largest of its terms, like max(), else the smallest, like min()
     * @param list<array{float, float}|self> $terms   The percentage of the room and the cells added to it, or a size of the same kind
     */
    public function __construct(public readonly bool $largest, public readonly array $terms)
    {
    }

    /**
     * The size in cells, unrounded, for a room.
     */
    public function at(float $room): float
    {
        $sizes = array_map(static fn (array|self $term) => $term instanceof self ? $term->at($room) : $room * $term[0] / 100 + $term[1], $this->terms);

        return $sizes ? $this->largest ? max($sizes) : min($sizes) : 0.0;
    }
}
