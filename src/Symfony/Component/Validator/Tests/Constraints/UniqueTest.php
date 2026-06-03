<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class UniqueTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(UniqueDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'UniqueDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);

        [$dConstraint] = $metadata->getPropertyMetadata('d')[0]->getConstraints();
        self::assertSame('intval', $dConstraint->normalizer);
    }
}

class UniqueDummy
{
    #[Unique]
    private $a;

    #[Unique(message: 'myMessage')]
    private $b;

    #[Unique(groups: ['my_group'], payload: 'some attached data')]
    private $c;

    #[Unique(normalizer: 'intval')]
    private $d;
}
