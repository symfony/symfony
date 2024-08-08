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
        new UnionType(Type::int(), new UnionType(Type::bool(), Type::float()));
    }

    /**
     * @dataProvider provideStandaloneTypes
     */
    public function testCannotCreateWithStandaloneParts(Type $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::generic(Type::int()), $type);
    }

    /**
     * @dataProvider provideComposableTypes
     */
    public function testCanCreateWithComposableTypes(Type $type): void
    {
        $this->assertContains($type, (new UnionType(Type::generic(Type::int()), $type))->getTypes());
    }

    public static function provideComposableTypes(): iterable
    {
        foreach (TypeIdentifier::cases() as $case) {
            if ($case->isComposable()) {
                yield $case->name => [Type::builtin($case)];
            }
        }
    }

    public static function provideStandaloneTypes(): iterable
    {
        foreach (TypeIdentifier::cases() as $case) {
            if (!$case->isComposable()) {
                yield $case->name => [Type::builtin($case)];
            }
        }
    }

    public function testCannotComposeTrueAndFalse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::true(), Type::false());
    }

    public function testCannotComposeBoolAndBoolValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UnionType(Type::bool(), Type::false());
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

        $type = new UnionType(Type::int(), Type::string(), Type::object('T'));
        $this->assertFalse($type->everyTypeIs(fn (Type $t) => $t instanceof BuiltinType));
    }

    public function testToString()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::float());
        $this->assertSame('float|int|string', (string) $type);

        $type = new UnionType(Type::int(), Type::string(), Type::intersection(Type::object('Foo'), Type::object('Bar')));
        $this->assertSame('(Bar&Foo)|int|string', (string) $type);
    }

    public function testIsNullable()
    {
        $this->assertFalse((new UnionType(Type::int(), Type::intersection(Type::object('Foo'), Type::object('Bar'))))->isNullable());
        $this->assertFalse((new UnionType(Type::int(), Type::string()))->isNullable());
        $this->assertTrue((new UnionType(Type::int(), Type::null()))->isNullable());
    }

    /**
     * @dataProvider provideTypeIdentifierAndParts
     */
    public function testGetTypeIdentifier(TypeIdentifier $expected, Type $first, Type $second): void
    {
        $this->assertSame($expected, (new UnionType($first, $second))->getTypeIdentifier());
    }

    public static function provideTypeIdentifierAndParts(): iterable
    {
        $int1 = Type::intersection(Type::object('Foo'), Type::object('Bar'));
        $int2 = Type::intersection(Type::object('Bar'), Type::object('Baz'));

        yield 'int|string' => [TypeIdentifier::MIXED, Type::int(), Type::string()];
        yield 'int|null' => [TypeIdentifier::MIXED, Type::int(), Type::null()];
        yield 'int|int<var>' => [TypeIdentifier::INT, Type::int(), Type::generic(Type::int())];
        yield 'int|object' => [TypeIdentifier::MIXED, Type::int(), Type::object()];
        yield 'intersections' => [TypeIdentifier::OBJECT, $int1, $int2];
        yield 'intersection|ClassName' => [TypeIdentifier::OBJECT, $int1, Type::object('Foo')];
        yield 'intersection|scalar' => [TypeIdentifier::MIXED, $int1, Type::int()];
        yield 'ClassName|ClassName<var>' => [TypeIdentifier::OBJECT, Type::object('Foo'), Type::generic(Type::object('Foo'))];
    }

    public function testIsA()
    {
        $type = new UnionType(Type::int(), Type::string(), Type::float());
        $this->assertFalse($type->isNullable());
        $this->assertFalse($type->isA(TypeIdentifier::ARRAY));

        $type = new UnionType(Type::int(), Type::string(), Type::intersection(Type::object('Foo'), Type::object('Bar')));
        $this->assertTrue($type->isA(TypeIdentifier::INT));
        $this->assertTrue($type->isA(TypeIdentifier::STRING));
        $this->assertFalse($type->isA(TypeIdentifier::FLOAT));
        $this->assertFalse($type->isA(TypeIdentifier::BOOL));
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
