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
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class LessThanOrEqualTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(LessThanOrEqualDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->getPropertyMetadata('a')[0]->getConstraints();
        self::assertSame(2, $aConstraint->value);
        self::assertNull($aConstraint->propertyPath);

        [$bConstraint] = $metadata->getPropertyMetadata('b')[0]->getConstraints();
        self::assertSame(4711, $bConstraint->value);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'LessThanOrEqualDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->getPropertyMetadata('c')[0]->getConstraints();
        self::assertNull($cConstraint->value);
        self::assertSame('b', $cConstraint->propertyPath);
        self::assertSame('myMessage', $cConstraint->message);
        self::assertSame(['foo'], $cConstraint->groups);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testDoctrineStyle()
    {
        $constraint = new LessThanOrEqual(['value' => 5]);

        $this->assertSame(5, $constraint->value);
    }
}

class LessThanOrEqualDummy
{
    #[LessThanOrEqual(2)]
    private $a;

    #[LessThanOrEqual(value: 4711, message: 'myMessage')]
    private $b;

    #[LessThanOrEqual(propertyPath: 'b', message: 'myMessage', groups: ['foo'])]
    private $c;
}
