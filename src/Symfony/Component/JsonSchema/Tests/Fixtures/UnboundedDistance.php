<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class UnboundedDistance
{
    public float $max = \INF;

    public float $ratio = \NAN;

    #[Assert\Range(min: 0, max: \INF)]
    public float $radius = 1.0;

    #[Assert\GreaterThan(-\INF)]
    #[Assert\LessThan(\INF)]
    public float $offset = 1.0;

    public function __construct(
        public float $min = -\INF,
    ) {
    }

    public function search(string $query, float $maxDistance = \INF): void
    {
    }
}
