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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
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

    #[DataProvider('provideTypesWithTheSameStringRepresentation')]
    public function testPreservesTypesWithTheSameStringRepresentation(Type $first, Type $second)
    {
        $this->assertSame((string) $first, (string) $second);

        foreach ([[$first, $second], [$second, $first]] as $types) {
            $this->assertSame($types, (new IntersectionType(...$types))->getTypes());
            $this->assertSame($types, (new IntersectionType(...[...$types, clone $first, clone $second]))->getTypes());
            $this->assertSame($first.'&'.$second, (string) new IntersectionType(...$types));
        }
    }

    public static function provideTypesWithTheSameStringRepresentation(): iterable
    {
        $generic = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        yield 'generic object and collection' => [$generic, new CollectionType($generic)];

        yield 'template bounds' => [Type::template('T', Type::object(\Countable::class)), Type::template('T', Type::object(\Traversable::class))];
        yield 'nested generic arguments' => [Type::generic(Type::object(\ArrayObject::class), Type::template('T', Type::int())), Type::generic(Type::object(\ArrayObject::class), Type::template('T', Type::string()))];
        yield 'nested collection arguments' => [Type::collection(Type::object(\ArrayObject::class), Type::template('T', Type::int())), Type::collection(Type::object(\ArrayObject::class), Type::template('T', Type::string()))];
        yield 'enum and object' => [Type::enum(DummyEnum::class), Type::object(DummyEnum::class)];
        yield 'backed enum and object' => [Type::enum(DummyBackedEnum::class), Type::object(DummyBackedEnum::class)];
    }

    public function testRemovesEquivalentTypes()
    {
        $generic = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        $equivalent = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        $type = new IntersectionType(Type::object(\Stringable::class), $generic, Type::object(\Countable::class), $equivalent, clone $generic, Type::object(\Countable::class));

        $this->assertEquals([$generic, Type::object(\Countable::class), Type::object(\Stringable::class)], $type->getTypes());
    }

    public function testAcceptsEveryTypeWithTheSameStringRepresentation()
    {
        $generic = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        $collection = new CollectionType($generic);
        $integers = new \ArrayObject([123]);
        $strings = new \ArrayObject(['value']);

        $this->assertTrue($generic->accepts($integers));
        $this->assertFalse($collection->accepts($integers));

        foreach ([[$generic, $collection], [$collection, $generic]] as $types) {
            $type = new IntersectionType(...$types);

            $this->assertFalse($type->accepts($integers));
            $this->assertTrue($type->accepts($strings));
        }
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
        $this->assertFalse((new IntersectionType(Type::collection(Type::object(\ArrayIterator::class), Type::int()), Type::object(\Countable::class)))->accepts(new \ArrayIterator(['a'])));
    }
}
