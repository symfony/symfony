<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class TypeFactoryTest extends TestCase
{
    public function testCreateBuiltin()
    {
        $this->assertEquals(new BuiltinType(TypeIdentifier::INT), Type::builtin(TypeIdentifier::INT));
        $this->assertEquals(new BuiltinType(TypeIdentifier::INT), Type::builtin('int'));
        $this->assertEquals(new BuiltinType(TypeIdentifier::INT), Type::int());
        $this->assertEquals(new BuiltinType(TypeIdentifier::FLOAT), Type::float());
        $this->assertEquals(new BuiltinType(TypeIdentifier::STRING), Type::string());
        $this->assertEquals(new BuiltinType(TypeIdentifier::BOOL), Type::bool());
        $this->assertEquals(new BuiltinType(TypeIdentifier::RESOURCE), Type::resource());
        $this->assertEquals(new BuiltinType(TypeIdentifier::FALSE), Type::false());
        $this->assertEquals(new BuiltinType(TypeIdentifier::TRUE), Type::true());
        $this->assertEquals(new BuiltinType(TypeIdentifier::CALLABLE), Type::callable());
        $this->assertEquals(new BuiltinType(TypeIdentifier::NULL), Type::null());
        $this->assertEquals(new BuiltinType(TypeIdentifier::MIXED), Type::mixed());
        $this->assertEquals(new BuiltinType(TypeIdentifier::VOID), Type::void());
        $this->assertEquals(new BuiltinType(TypeIdentifier::NEVER), Type::never());
    }

    public function testCreateArray()
    {
        $this->assertEquals(new CollectionType(new BuiltinType(TypeIdentifier::ARRAY)), Type::array());

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)),
                new BuiltinType(TypeIdentifier::BOOL),
            )),
            Type::array(Type::bool()),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::STRING),
                new BuiltinType(TypeIdentifier::BOOL),
            )),
            Type::array(Type::bool(), Type::string()),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::BOOL),
            ), isList: true),
            Type::array(Type::bool(), Type::int(), true),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::MIXED),
            ), isList: true),
            Type::list(),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::BOOL),
            ), isList: true),
            Type::list(Type::bool()),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::STRING),
                new BuiltinType(TypeIdentifier::MIXED),
            )),
            Type::dict(),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::STRING),
                new BuiltinType(TypeIdentifier::BOOL),
            )),
            Type::dict(Type::bool()),
        );
    }

    public function testCreateIterable()
    {
        $this->assertEquals(new CollectionType(new BuiltinType(TypeIdentifier::ITERABLE)), Type::iterable());

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ITERABLE),
                new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)),
                new BuiltinType(TypeIdentifier::BOOL),
            )),
            Type::iterable(Type::bool()),
        );

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ITERABLE),
                new BuiltinType(TypeIdentifier::STRING),
                new BuiltinType(TypeIdentifier::BOOL),
            )),
            Type::iterable(Type::bool(), Type::string()),
        );
    }

    public function testCreateObject()
    {
        $this->assertEquals(new BuiltinType(TypeIdentifier::OBJECT), Type::object());
        $this->assertEquals(new ObjectType(self::class), Type::object(self::class));
    }

    public function testCreateEnum()
    {
        $this->assertEquals(new EnumType(DummyEnum::class), Type::enum(DummyEnum::class));
        $this->assertEquals(new BackedEnumType(DummyBackedEnum::class, new BuiltinType(TypeIdentifier::STRING)), Type::enum(DummyBackedEnum::class));
        $this->assertEquals(
            new BackedEnumType(DummyBackedEnum::class, new BuiltinType(TypeIdentifier::INT)),
            Type::enum(DummyBackedEnum::class, new BuiltinType(TypeIdentifier::INT)),
        );
    }

    public function testCreateGeneric()
    {
        $this->assertEquals(
            new GenericType(new ObjectType(self::class), new BuiltinType(TypeIdentifier::INT)),
            Type::generic(Type::object(self::class), Type::int()),
        );
    }

    public function testCreateTemplate()
    {
        $this->assertEquals(new TemplateType('T', new BuiltinType(TypeIdentifier::INT)), Type::template('T', Type::int()));
        $this->assertEquals(new TemplateType('T', Type::mixed()), Type::template('T'));
    }

    public function testCreateUnion()
    {
        $this->assertEquals(new UnionType(new BuiltinType(TypeIdentifier::INT), new ObjectType(self::class)), Type::union(Type::int(), Type::object(self::class)));
        $this->assertEquals(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)), Type::union(Type::int(), Type::string(), Type::int()));
        $this->assertEquals(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)), Type::union(Type::int(), Type::union(Type::int(), Type::string())));
    }

    public function testCreateIntersection()
    {
        $this->assertEquals(new IntersectionType(new ObjectType(\DateTime::class), new ObjectType(self::class)), Type::intersection(Type::object(\DateTime::class), Type::object(self::class)));
        $this->assertEquals(new IntersectionType(new ObjectType(\DateTime::class), new ObjectType(self::class)), Type::intersection(Type::object(\DateTime::class), Type::object(self::class), Type::object(self::class)));
        $this->assertEquals(new IntersectionType(new ObjectType(\DateTime::class), new ObjectType(self::class)), Type::intersection(Type::object(\DateTime::class), Type::intersection(Type::object(\DateTime::class), Type::object(self::class))));
    }

    public function testCreateNullable()
    {
        $this->assertEquals(new NullableType(new BuiltinType(TypeIdentifier::INT)), Type::nullable(Type::int()));
        $this->assertEquals(new NullableType(new BuiltinType(TypeIdentifier::INT)), Type::nullable(Type::nullable(Type::int())));
        $this->assertEquals(new BuiltinType(TypeIdentifier::MIXED), Type::nullable(Type::mixed()));

        $this->assertEquals(
            new NullableType(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING))),
            Type::nullable(Type::union(Type::int(), Type::string())),
        );
        $this->assertEquals(
            new NullableType(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING))),
            Type::nullable(Type::union(Type::int(), Type::string(), Type::null())),
        );
        $this->assertEquals(
            new NullableType(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING))),
            Type::union(Type::nullable(Type::int()), Type::string()),
        );
        $this->assertEquals(
            new NullableType(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING))),
            Type::union(Type::nullable(Type::union(Type::int(), Type::string())), Type::string()),
        );
    }

    public function testCreateArrayShape()
    {
        $this->assertEquals(new ArrayShapeType(['foo' => ['type' => Type::bool(), 'optional' => true]]), Type::arrayShape(['foo' => ['type' => Type::bool(), 'optional' => true]]));
        $this->assertEquals(new ArrayShapeType(['foo' => ['type' => Type::bool(), 'optional' => false]]), Type::arrayShape(['foo' => Type::bool()]));
        $this->assertEquals(new ArrayShapeType(
            shape: ['foo' => ['type' => Type::bool(), 'optional' => false]],
            extraKeyType: Type::arrayKey(),
            extraValueType: Type::mixed(),
        ), Type::arrayShape(['foo' => Type::bool()], sealed: false));
        $this->assertEquals(new ArrayShapeType(
            shape: ['foo' => ['type' => Type::bool(), 'optional' => false]],
            extraKeyType: Type::string(),
            extraValueType: Type::bool(),
        ), Type::arrayShape(['foo' => Type::bool()], extraKeyType: Type::string(), extraValueType: Type::bool()));
    }

    public function testCreateArrayKey()
    {
        $this->assertEquals(new UnionType(Type::int(), Type::string()), Type::arrayKey());
    }

    #[DataProvider('createFromValueProvider')]
    public function testCreateFromValue(Type $expected, mixed $value)
    {
        $this->assertEquals($expected, Type::fromValue($value));
    }

    /**
     * @return iterable<array{0: Type, 1: mixed}>
     */
    public static function createFromValueProvider(): iterable
    {
        // builtin
        yield [Type::null(), null];
        yield [Type::true(), true];
        yield [Type::false(), false];
        yield [Type::int(), 1];
        yield [Type::float(), 1.1];
        yield [Type::string(), 'string'];
        yield [Type::callable(), strtoupper(...)];
        yield [Type::resource(), fopen('php://temp', 'r')];

        // object
        yield [Type::object(\DateTimeImmutable::class), new \DateTimeImmutable()];
        yield [Type::object(), new \stdClass()];
        yield [Type::list(Type::object()), [new \stdClass(), new \DateTimeImmutable()]];
        yield [Type::enum(DummyEnum::class), DummyEnum::ONE];
        yield [Type::enum(DummyBackedEnum::class), DummyBackedEnum::ONE];

        // collection
        $arrayAccess = new class implements \ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return true;
            }

            public function offsetGet(mixed $offset): mixed
            {
                return null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
            }

            public function offsetUnset(mixed $offset): void
            {
            }
        };

        yield [Type::array(Type::mixed()), []];
        yield [Type::list(Type::int()), [1, 2, 3]];
        yield [Type::dict(Type::bool()), ['a' => true, 'b' => false]];
        yield [Type::array(Type::string()), [1 => 'foo', 'bar' => 'baz']];
        yield [Type::array(Type::nullable(Type::bool()), Type::int()), [1 => true, 2 => null, 3 => false]];
        yield [Type::collection(Type::object(\ArrayIterator::class), Type::mixed(), Type::arrayKey()), new \ArrayIterator()];
        yield [Type::collection(Type::object(\Generator::class), Type::string(), Type::int()), (fn (): iterable => yield 'string')()];
        yield [Type::collection(Type::object($arrayAccess::class)), $arrayAccess];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testCannotCreateIterableList()
    {
        $this->expectUserDeprecationMessage('Since symfony/type-info 7.3: The third argument of "Symfony\Component\TypeInfo\TypeFactoryTrait::iterable()" is deprecated. Use the "Symfony\Component\TypeInfo\Type::list()" method to create a list instead.');
        Type::iterable(key: Type::int(), asList: true);
    }
}
