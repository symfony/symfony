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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;

class UnionTypeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int());
    }

    public function testCannotCreateWithUnionTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int(), new UnionType());
    }

    public function testCannotCreateWithNullPart()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int(), Type::null());
    }

    public function testCannotCreateWithStandaloneTypePart()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int(), Type::mixed());
    }

    public function testCannotCreateWithTrueAndFalseTypeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::true(), Type::false());
    }

    public function testCannotCreateWithMultipleBooleanTypeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::true(), Type::bool());
    }

    public function testCannotCreateWithBuiltinObjectAndClassTypeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::object(), Type::object(\DateTime::class));
    }

    public function testSortTypesOnCreation()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::bool());
        $this->assertEquals([Type::bool(), Type::int(), Type::string()], $type->getTypes());
    }

    public function testComposedTypesAreSatisfiedBy()
    {
        $type = new UnionType(Type::object(\Iterator::class), Type::int());
        $this->assertTrue($type->composedTypesAreSatisfiedBy(static fn (Type $t): bool => $t instanceof BuiltinType));

        $type = new UnionType(Type::int(), Type::string());
        $this->assertFalse($type->composedTypesAreSatisfiedBy(static fn (Type $t): bool => $t instanceof ObjectType));
    }

    public function testToString()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::float());
        $this->assertSame('float|int|string', (string) $type);

        $type = new UnionType(Type::int(), Type::string(), Type::intersection(Type::object(\DateTime::class), Type::object(\Iterator::class)));
        $this->assertSame(\sprintf('(%s&%s)|int|string', \DateTime::class, \Iterator::class), (string) $type);
    }

    public function testAccepts()
    {
        $type = new UnionType(Type::int(), Type::bool());

        $this->assertFalse($type->accepts('string'));
        $this->assertTrue($type->accepts(123));
        $this->assertTrue($type->accepts(false));
    }
}
