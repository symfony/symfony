<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php80PromotedDummyWithDocblock
{
    /**
     * @param \DateTime[] $datesWithIncoherentDocBlock
     */
    public function __construct(
        /**
         * @var \DateTimeImmutable[]
         */
        private array $dates,
        /**
         * @var \DateTimeImmutable[] $datesWithIncoherentDocBlock
         */
        private array $datesWithIncoherentDocBlock,
    ) {
    }

    public function getDates(): array
    {
        return $this->dates;
    }

    public function getDatesWithIncoherentDocBlock(): array
    {
        return $this->datesWithIncoherentDocBlock;
    }
}
