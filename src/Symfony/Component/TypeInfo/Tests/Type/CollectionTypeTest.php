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
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class CollectionTypeTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testCannotCreateInvalidBuiltinType()
    {
        $this->expectException(InvalidArgumentException::class);
        new CollectionType(Type::int());
    }

    public function testCanOnlyConstructListWithIntKeyType()
    {
        new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::bool()), isList: true);
        $this->addToAssertionCount(1);

        $this->expectException(InvalidArgumentException::class);
        new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool()), isList: true);
    }

    public function testIsList()
    {
        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::bool()));
        $this->assertFalse($type->isList());

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::bool()), isList: true);
        $this->assertTrue($type->isList());
    }

    public function testGetCollectionKeyType()
    {
        $type = new CollectionType(Type::builtin(TypeIdentifier::ARRAY));
        $this->assertEquals(Type::union(Type::int(), Type::string()), $type->getCollectionKeyType());

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::bool()));
        $this->assertEquals(Type::int(), $type->getCollectionKeyType());

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool()));
        $this->assertEquals(Type::string(), $type->getCollectionKeyType());
    }

    public function testGetCollectionValueType()
    {
        $type = new CollectionType(Type::builtin(TypeIdentifier::ARRAY));
        $this->assertEquals(Type::mixed(), $type->getCollectionValueType());

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::bool()));
        $this->assertEquals(Type::bool(), $type->getCollectionValueType());

        $type = new CollectionType(new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool()));
        $this->assertEquals(Type::bool(), $type->getCollectionValueType());
    }

    public function testWrappedTypeIsSatisfiedBy()
    {
        $type = new CollectionType(Type::builtin(TypeIdentifier::ARRAY));
        $this->assertTrue($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'array' === (string) $t));

        $type = new CollectionType(Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertFalse($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'array' === (string) $t));
    }

    public function testToString()
    {
        $type = new CollectionType(Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertEquals('iterable', (string) $type);

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::bool()));
        $this->assertEquals('array<bool>', (string) $type);

        $type = new CollectionType(new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool()));
        $this->assertEquals('array<string,bool>', (string) $type);
    }

    public function testAccepts()
    {
        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool()));

        $this->assertFalse($type->accepts(new \ArrayObject(['foo' => true, 'bar' => true])));

        $this->assertTrue($type->accepts(['foo' => true, 'bar' => true]));
        $this->assertFalse($type->accepts(['foo' => true, 'bar' => 123]));
        $this->assertFalse($type->accepts([1 => true]));

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::bool()));

        $this->assertTrue($type->accepts([1 => true]));
        $this->assertFalse($type->accepts(['foo' => true]));

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::bool()), isList: true);

        $this->assertTrue($type->accepts([0 => true, 1 => false]));
        $this->assertFalse($type->accepts([0 => true, 2 => false]));

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ITERABLE), Type::string(), Type::bool()));

        $this->assertTrue($type->accepts(new \ArrayObject(['foo' => true, 'bar' => true])));
        $this->assertFalse($type->accepts(new \ArrayObject(['foo' => true, 'bar' => 123])));
        $this->assertFalse($type->accepts(new \ArrayObject([1 => true])));

        $type = new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ITERABLE), Type::int(), Type::bool()));

        $this->assertTrue($type->accepts(new \ArrayObject([1 => true])));
        $this->assertFalse($type->accepts(new \ArrayObject(['foo' => true])));
        $this->assertTrue($type->accepts(new \ArrayObject([0 => true, 1 => false])));
        $this->assertFalse($type->accepts(new \ArrayObject([0 => true, 1 => 'string'])));
    }

    /**
     * @group legacy
     */
    public function testCannotCreateIterableList()
    {
        $this->expectUserDeprecationMessage('Since symfony/type-info 7.3: Creating a "Symfony\Component\TypeInfo\Type\CollectionType" that is a list and not an array is deprecated and will throw a "Symfony\Component\TypeInfo\Exception\InvalidArgumentException" in 8.0.');
        new CollectionType(Type::generic(Type::builtin(TypeIdentifier::ITERABLE), Type::bool()), isList: true);
    }

    public function testMergeCollectionValueTypes()
    {
        $this->assertEquals(Type::int(), CollectionType::mergeCollectionValueTypes([Type::int()]));
        $this->assertEquals(Type::union(Type::int(), Type::string()), CollectionType::mergeCollectionValueTypes([Type::int(), Type::string()]));

        $this->assertEquals(Type::mixed(), CollectionType::mergeCollectionValueTypes([Type::int(), Type::mixed()]));

        $this->assertEquals(Type::union(Type::int(), Type::true()), CollectionType::mergeCollectionValueTypes([Type::int(), Type::true()]));
        $this->assertEquals(Type::bool(), CollectionType::mergeCollectionValueTypes([Type::true(), Type::false(), Type::true()]));

        $this->assertEquals(Type::union(Type::object(\Stringable::class), Type::object(\Countable::class)), CollectionType::mergeCollectionValueTypes([Type::object(\Stringable::class), Type::object(\Countable::class)]));
        $this->assertEquals(Type::object(), CollectionType::mergeCollectionValueTypes([Type::object(\Stringable::class), Type::object()]));
    }

    public function testCannotMergeEmptyCollectionValueTypes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The $types cannot be empty.');

        CollectionType::mergeCollectionValueTypes([]);
    }
}
