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
    public function __construct(
        /**
         * @var \DateTimeImmutable[]
         */
        private array $dates,
    ) {
    }

    public function getDates(): array
    {
        return $this->dates;
    }
}
