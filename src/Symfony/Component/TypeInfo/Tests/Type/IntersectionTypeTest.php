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
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class IntersectionTypeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::int());
    }

    public function testCannotCreateWithIntersectionTypeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::int(), new IntersectionType());
    }

    public function testSortTypesOnCreation()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::bool());
        $this->assertEquals([Type::bool(), Type::int(), Type::string()], $type->getTypes());
    }

    public function testAtLeastOneTypeIs()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::bool());

        $this->assertTrue($type->atLeastOneTypeIs(fn (Type $t) => 'int' === (string) $t));
        $this->assertFalse($type->atLeastOneTypeIs(fn (Type $t) => 'float' === (string) $t));
    }

    public function testEveryTypeIs()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::bool());
        $this->assertTrue($type->everyTypeIs(fn (Type $t) => $t instanceof BuiltinType));

        $type = new IntersectionType(Type::int(), Type::string(), Type::template('T'));
        $this->assertFalse($type->everyTypeIs(fn (Type $t) => $t instanceof BuiltinType));
    }

    public function testToString()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::float());
        $this->assertSame('float&int&string', (string) $type);

        $type = new IntersectionType(Type::int(), Type::string(), Type::union(Type::float(), Type::bool()));
        $this->assertSame('(bool|float)&int&string', (string) $type);
    }

    public function testIsNullable()
    {
        $this->assertFalse((new IntersectionType(Type::int(), Type::string(), Type::float()))->isNullable());
        $this->assertTrue((new IntersectionType(Type::null(), Type::union(Type::int(), Type::mixed())))->isNullable());
    }

    public function testAsNonNullable()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::float());

        $this->assertSame($type, $type->asNonNullable());
    }

    public function testCannotTurnNullIntersectionAsNonNullable()
    {
        $this->expectException(LogicException::class);

        $type = (new IntersectionType(Type::null(), Type::mixed()))->asNonNullable();
    }

    public function testIsA()
    {
        $type = new IntersectionType(Type::int(), Type::string(), Type::float());
        $this->assertFalse($type->isA(TypeIdentifier::ARRAY));

        $type = new IntersectionType(Type::int(), Type::string(), Type::union(Type::float(), Type::bool()));
        $this->assertFalse($type->isA(TypeIdentifier::INT));

        $type = new IntersectionType(Type::int(), Type::union(Type::int(), Type::int()));
        $this->assertTrue($type->isA(TypeIdentifier::INT));
    }
}
