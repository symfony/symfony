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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

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

    #[DataProvider('provideTypesWithTheSameStringRepresentation')]
    public function testPreservesTypesWithTheSameStringRepresentation(Type $first, Type $second)
    {
        $this->assertSame((string) $first, (string) $second);

        foreach ([[$first, $second], [$second, $first]] as $types) {
            $this->assertSame($types, (new UnionType(...$types))->getTypes());
            $this->assertSame($types, (new UnionType(...[...$types, clone $first, clone $second]))->getTypes());
            $this->assertSame($first.'|'.$second, (string) new UnionType(...$types));
        }
    }

    public static function provideTypesWithTheSameStringRepresentation(): iterable
    {
        $generic = Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::string());
        yield 'generic array and collection' => [$generic, new CollectionType($generic)];

        $generic = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        yield 'generic object and collection' => [$generic, new CollectionType($generic)];

        yield 'template bounds' => [Type::template('T', Type::int()), Type::template('T', Type::string())];
        yield 'nested generic arguments' => [Type::generic(Type::object(\ArrayObject::class), Type::template('T', Type::int())), Type::generic(Type::object(\ArrayObject::class), Type::template('T', Type::string()))];
        yield 'nested collection arguments' => [Type::array(Type::template('T', Type::int())), Type::array(Type::template('T', Type::string()))];
        yield 'enum and object' => [Type::enum(DummyEnum::class), Type::object(DummyEnum::class)];
        yield 'backed enum and object' => [Type::enum(DummyBackedEnum::class), Type::object(DummyBackedEnum::class)];
    }

    public function testRemovesEquivalentTypes()
    {
        $generic = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        $equivalent = Type::generic(Type::object(\ArrayObject::class), Type::int(), Type::string());
        $type = new UnionType(Type::bool(), $generic, Type::string(), $equivalent, Type::int(), clone $generic, Type::bool(), Type::int());

        $this->assertEquals([$generic, Type::bool(), Type::int(), Type::string()], $type->getTypes());
    }

    public function testAcceptsEitherTypeWithTheSameStringRepresentation()
    {
        $generic = Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::string());
        $collection = new CollectionType($generic);

        $this->assertTrue($generic->accepts([123]));
        $this->assertFalse($collection->accepts([123]));

        foreach ([[$generic, $collection], [$collection, $generic]] as $types) {
            $this->assertTrue((new UnionType(...$types))->accepts([123]));
        }
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
        $this->assertFalse((new UnionType(Type::list(Type::int()), Type::string()))->accepts(['a']));
        $this->assertFalse((new UnionType(Type::arrayShape(['a' => Type::int()]), Type::string()))->accepts(['a' => 'x']));
    }
}
