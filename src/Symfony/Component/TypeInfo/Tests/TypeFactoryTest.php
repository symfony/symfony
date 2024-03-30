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

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
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

        $this->assertEquals(
            new CollectionType(new GenericType(
                new BuiltinType(TypeIdentifier::ITERABLE),
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::BOOL),
            ), isList: true),
            Type::iterable(Type::bool(), Type::int(), true),
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
        $this->assertEquals(new IntersectionType(new BuiltinType(TypeIdentifier::INT), new ObjectType(self::class)), Type::intersection(Type::int(), Type::object(self::class)));
        $this->assertEquals(new IntersectionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)), Type::intersection(Type::int(), Type::string(), Type::int()));
        $this->assertEquals(new IntersectionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING)), Type::intersection(Type::int(), Type::intersection(Type::int(), Type::string())));
    }

    public function testCreateNullable()
    {
        $this->assertEquals(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::NULL)), Type::nullable(Type::int()));
        $this->assertEquals(new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::NULL)), Type::nullable(Type::nullable(Type::int())));

        $this->assertEquals(
            new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING), new BuiltinType(TypeIdentifier::NULL)),
            Type::nullable(Type::union(Type::int(), Type::string())),
        );
        $this->assertEquals(
            new UnionType(new BuiltinType(TypeIdentifier::INT), new BuiltinType(TypeIdentifier::STRING), new BuiltinType(TypeIdentifier::NULL)),
            Type::nullable(Type::union(Type::int(), Type::string(), Type::null())),
        );
    }
}
