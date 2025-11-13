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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class DateTimeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(DateTimeDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame('Y-m-d H:i:s', $aConstraint->format);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame('d.m.Y', $bConstraint->format);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'DateTimeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertSame('m/d/Y', $cConstraint->format);
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testDoctrineStyle()
    {
        $constraint = new DateTime(['format' => 'm/d/Y']);

        $this->assertSame('m/d/Y', $constraint->format);
    }
}

class DateTimeDummy
{
    #[DateTime]
    private $a;

    #[DateTime(format: 'd.m.Y', message: 'myMessage')]
    private $b;

    #[DateTime('m/d/Y', groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
