<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Arc
{
    /**
     * @param non-empty-string $place  The place name
     * @param int<1, max>      $weight The weight of the arc (must be greater than 0)
     */
    public function __construct(
        public readonly string $place,
        public readonly int $weight,
    ) {
        if ($weight < 1) {
            throw new \InvalidArgumentException(\sprintf('The weight must be greater than 0, %d given.', $weight));
        }
        if (!$place) {
            throw new \InvalidArgumentException('The place name cannot be empty.');
        }
    }
}
