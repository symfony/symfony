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


class ConstructorDummyWithVarTagsDocBlock
{
    public function __construct(
        public \DateTimeZone $timezone,
        /** @var int */
        public $date,
        /** @var \DateTimeInterface */
        public $dateObject,
        public \DateTimeImmutable $dateTime,
        public $mixed,
        /** @var ConstructorDummy[] */
        public array $objectsArray,
    )
    {
    }
}
