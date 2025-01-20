<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Type;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

class IntersectionTypeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object(\DateTime::class));
    }

    public function testCannotCreateWithUnionTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object(\DateTime::class), new UnionType(Type::int(), Type::string()));
    }

    public function testCannotCreateWithIntersectionTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object(\DateTime::class), new IntersectionType(Type::object(\DateTime::class), Type::object(\Iterator::class)));
    }

    public function testCannotCreateWithNonObjectTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object(\DateTime::class), Type::int());
    }

    public function testCannotCreateWithNullableTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object(\DateTime::class), Type::nullable(Type::object(\Stringable::class)));
    }

    public function testCanCreateWithWrappingTypes()
    {
        new IntersectionType(Type::collection(Type::object(\Iterator::class)), Type::generic(Type::object(\Iterator::class)));
        // no assertion. this method just asserts that no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testSortTypesOnCreation()
    {
        $type = new IntersectionType(Type::object(\DateTime::class), Type::object(\Iterator::class), Type::object(\Stringable::class));
        $this->assertEquals([Type::object(\DateTime::class), Type::object(\Iterator::class), Type::object(\Stringable::class)], $type->getTypes());
    }

    public function testComposedTypesAreSatisfiedBy()
    {
        $type = new IntersectionType(Type::object(\Iterator::class), Type::object(\Stringable::class));
        $this->assertTrue($type->composedTypesAreSatisfiedBy(static fn (Type $t): bool => $t instanceof ObjectType));

        $type = new IntersectionType(Type::object(\Iterator::class), Type::object(\Stringable::class));
        $this->assertFalse($type->composedTypesAreSatisfiedBy(static fn (ObjectType $t): bool => \Iterator::class === $t->getClassName()));
    }

    public function testToString()
    {
        $type = new IntersectionType(Type::object(\DateTime::class), Type::object(\Iterator::class), Type::object(\Stringable::class));
        $this->assertSame(\sprintf('%s&%s&%s', \DateTime::class, \Iterator::class, \Stringable::class), (string) $type);
    }

    public function testAccepts()
    {
        $type = new IntersectionType(Type::object(\Traversable::class), Type::object(\Countable::class));

        $traversableAndCountable = new \ArrayObject();

        $countable = new class implements \Countable {
            public function count(): int
            {
                return 1;
            }
        };

        $this->assertFalse($type->accepts('string'));
        $this->assertFalse($type->accepts($countable));
        $this->assertTrue($type->accepts($traversableAndCountable));
    }
}
