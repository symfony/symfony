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
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class UnionTypeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int());
    }

    public function testCannotCreateWithUnionTypeParts()
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::int(), new UnionType());
    }

    public function testSortTypesOnCreation()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::bool());
        $this->assertEquals([Type::bool(), Type::int(), Type::string()], $type->getTypes());
    }

    public function testAsNonNullable()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::bool());
        $this->assertInstanceOf(UnionType::class, $type->asNonNullable());
        $this->assertEquals([Type::bool(), Type::int(), Type::string()], $type->asNonNullable()->getTypes());

        $type = new UnionType(Type::int(), Type::string(), Type::null());
        $this->assertInstanceOf(UnionType::class, $type->asNonNullable());
        $this->assertEquals([Type::int(), Type::string()], $type->asNonNullable()->getTypes());

        $type = new UnionType(Type::int(), Type::null());
        $this->assertInstanceOf(BuiltinType::class, $type->asNonNullable());
        $this->assertEquals(Type::int(), $type->asNonNullable());

        $type = new UnionType(Type::int(), Type::object(\stdClass::class), Type::mixed());
        $this->assertInstanceOf(UnionType::class, $type->asNonNullable());
        $this->assertEquals([
            Type::builtin(TypeIdentifier::ARRAY),
            Type::bool(),
            Type::float(),
            Type::int(),
            Type::object(),
            Type::resource(),
            Type::object(\stdClass::class),
            Type::string(),
        ], $type->asNonNullable()->getTypes());
    }

    public function testGetBaseType()
    {
        $this->assertEquals(Type::string(), (new UnionType(Type::string(), Type::null()))->getBaseType());

        $this->expectException(LogicException::class);
        (new UnionType(Type::string(), Type::int(), Type::null()))->getBaseType();
    }

    public function testAtLeastOneTypeIs()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::bool());

        $this->assertTrue($type->atLeastOneTypeIs(fn (Type $t) => 'int' === (string) $t));
        $this->assertFalse($type->atLeastOneTypeIs(fn (Type $t) => 'float' === (string) $t));
    }

    public function testEveryTypeIs()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::bool());
        $this->assertTrue($type->everyTypeIs(fn (Type $t) => $t instanceof BuiltinType));

        $type = new UnionType(Type::int(), Type::string(), Type::template('T'));
        $this->assertFalse($type->everyTypeIs(fn (Type $t) => $t instanceof BuiltinType));
    }

    public function testToString()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::float());
        $this->assertSame('float|int|string', (string) $type);

        $type = new UnionType(Type::int(), Type::string(), Type::intersection(Type::float(), Type::bool()));
        $this->assertSame('(bool&float)|int|string', (string) $type);
    }

    public function testIsNullable()
    {
        $this->assertFalse((new UnionType(Type::int(), Type::intersection(Type::float(), Type::int())))->isNullable());
        $this->assertTrue((new UnionType(Type::int(), Type::null()))->isNullable());
        $this->assertTrue((new UnionType(Type::int(), Type::mixed()))->isNullable());
    }

    public function testIsA()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::float());
        $this->assertFalse($type->isNullable());
        $this->assertFalse($type->isA(TypeIdentifier::ARRAY));

        $type = new UnionType(Type::int(), Type::string(), Type::intersection(Type::float(), Type::bool()));
        $this->assertTrue($type->isA(TypeIdentifier::INT));
        $this->assertTrue($type->isA(TypeIdentifier::STRING));
        $this->assertFalse($type->isA(TypeIdentifier::FLOAT));
        $this->assertFalse($type->isA(TypeIdentifier::BOOL));

        $type = new UnionType(Type::string(), Type::intersection(Type::int(), Type::int()));
        $this->assertTrue($type->isA(TypeIdentifier::INT));
    }

    public function testProxiesMethodsToNonNullableType()
    {
        $this->assertEquals(Type::string(), (new UnionType(Type::list(Type::string()), Type::null()))->getCollectionValueType());

        try {
            (new UnionType(Type::int(), Type::null()))->getCollectionValueType();
            $this->fail();
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new UnionType(Type::list(Type::string()), Type::string()))->getCollectionValueType();
            $this->fail();
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }
}
