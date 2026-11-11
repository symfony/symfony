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

class ValidatedRefinedProperties
{
    /** @var int<1, 10> */
    #[Assert\Range(min: 0, max: 20)]
    public int $looser;

    /** @var int<1, 10> */
    #[Assert\Range(min: 2, max: 8)]
    public int $stricter;

    /** @var int<1, 10> */
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(5)]
    public int $mixed;

    /** @var int<5, 10> */
    #[Assert\GreaterThan(0)]
    #[Assert\LessThan(100)]
    public int $looserExclusive;

    /** @var int<1, 10> */
    #[Assert\GreaterThan(5)]
    public int $stricterExclusive;

    #[Assert\GreaterThan(0)]
    #[Assert\GreaterThanOrEqual(5)]
    public int $inclusiveAfterExclusive;
}
