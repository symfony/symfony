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
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectShapeType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeMismatch;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class TypeTest extends TestCase
{
    public function testIsIdentifiedBy()
    {
        $this->assertTrue(Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class))->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::union(Type::int(), Type::string())->isIdentifiedBy(TypeIdentifier::INT));
        $this->assertTrue(Type::collection(Type::object(\Iterator::class))->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::generic(Type::object(\Iterator::class), Type::string())->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::nullable(Type::union(Type::collection(Type::object(\Iterator::class)), Type::string()))->isIdentifiedBy(TypeIdentifier::OBJECT));
    }

    public function testIsNullable()
    {
        $this->assertTrue(Type::null()->isNullable());
        $this->assertTrue(Type::mixed()->isNullable());
        $this->assertTrue(Type::nullable(Type::int())->isNullable());

        $this->assertFalse(Type::int()->isNullable());
    }

    public function testIsSatisfiedBy()
    {
        $this->assertTrue(Type::union(Type::int(), Type::string())->isSatisfiedBy(static fn (Type $t): bool => 'int' === (string) $t));
        $this->assertTrue(Type::union(Type::int(), Type::string())->isSatisfiedBy(static fn (Type $t): bool => $t instanceof UnionType));
        $this->assertTrue(Type::list(Type::int())->isSatisfiedBy(static fn (Type $t): bool => $t instanceof CollectionType && 'int' === (string) $t->getCollectionValueType()));
        $this->assertFalse(Type::list(Type::int())->isSatisfiedBy(static fn (Type $t): bool => 'int' === (string) $t));
    }

    public function testTraverse()
    {
        $this->assertEquals([Type::int()], iterator_to_array(Type::int()->traverse()));

        $this->assertEquals(
            [Type::union(Type::int(), Type::string()), Type::int(), Type::string()],
            iterator_to_array(Type::union(Type::int(), Type::string())->traverse()),
        );
        $this->assertEquals(
            [Type::union(Type::int(), Type::string())],
            iterator_to_array(Type::union(Type::int(), Type::string())->traverse(false)),
        );

        $this->assertEquals(
            [Type::generic(Type::object(\Traversable::class), Type::string()), Type::object(\Traversable::class)],
            iterator_to_array(Type::generic(Type::object(\Traversable::class), Type::string())->traverse()),
        );
        $this->assertEquals(
            [Type::generic(Type::object(\Traversable::class), Type::string())],
            iterator_to_array(Type::generic(Type::object(\Traversable::class), Type::string())->traverse(true, false)),
        );

        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int(), Type::null()],
            iterator_to_array(Type::nullable(Type::int())->traverse()),
        );
        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int()],
            iterator_to_array(Type::nullable(Type::int())->traverse(false)),
        );
        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int(), Type::null()],
            iterator_to_array(Type::nullable(Type::int())->traverse(true, false)),
        );
        $this->assertEquals(
            [Type::nullable(Type::int())],
            iterator_to_array(Type::nullable(Type::int())->traverse(false, false)),
        );
    }

    #[DataProvider('mapDataProvider')]
    public function testMap(Type $type, Type $expected)
    {
        $this->assertSame($type, $type->map(static fn (Type $t): Type => $t));
        $this->assertEquals($expected, $type->map(self::replaceTemplate(Type::int())));
    }

    /**
     * @return iterable<array{0: Type, 1: Type}>
     */
    public static function mapDataProvider(): iterable
    {
        $t = Type::template('T');

        yield [Type::int(), Type::int()];
        yield [$t, Type::int()];
        yield [Type::template('U', $t), Type::template('U', Type::int())];
        yield [Type::union($t, Type::string()), Type::union(Type::int(), Type::string())];
        yield [Type::nullable($t), Type::nullable(Type::int())];
        yield [Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class)), Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class))];
        yield [Type::generic(Type::object(\Iterator::class), $t), Type::generic(Type::object(\Iterator::class), Type::int())];
        yield [Type::list($t), Type::list(Type::int())];
        yield [
            Type::arrayShape(['a' => Type::list($t), 'b' => ['type' => Type::string(), 'optional' => true]], false, Type::template('T', Type::string()), $t),
            Type::arrayShape(['a' => Type::list(Type::int()), 'b' => ['type' => Type::string(), 'optional' => true]], false, Type::int(), Type::int()),
        ];
        yield [
            Type::objectShape(['a' => $t, 'b' => ['type' => Type::string(), 'optional' => true]]),
            Type::objectShape(['a' => Type::int(), 'b' => ['type' => Type::string(), 'optional' => true]]),
        ];
        yield [$shape = new ArrayShapeType(['a' => ['optional' => false, 'type' => Type::int()]]), $shape];
        yield [$shape = new ObjectShapeType(['a' => ['optional' => false, 'type' => Type::int()]]), $shape];
    }

    public function testMapVisitsTypesBottomUp()
    {
        $visited = [];
        Type::nullable(Type::list(Type::string()))->map(static function (Type $t) use (&$visited): Type {
            $visited[] = (string) $t;

            return $t;
        });

        $this->assertSame(['array', 'int', 'string', 'array<int, string>', 'list<string>', 'list<string>|null'], $visited);
    }

    #[DataProvider('mapRebuiltTypeDataProvider')]
    public function testMapGivesRebuiltTypesToMapper(Type $type, string $rebuiltType)
    {
        $mapped = Type::list($type)->map(static fn (Type $t): Type => match (true) {
            $t instanceof TemplateType && 'T' === $t->getName() => Type::object(\Countable::class),
            $rebuiltType === (string) $t => Type::bool(),
            default => $t,
        });

        $this->assertEquals(Type::list(Type::bool()), $mapped);
    }

    /**
     * @return iterable<array{0: Type, 1: string}>
     */
    public static function mapRebuiltTypeDataProvider(): iterable
    {
        $t = Type::template('T', Type::object(\Stringable::class));

        yield [Type::union($t, Type::string()), 'Countable|string'];
        yield [Type::nullable($t), 'Countable|null'];
        yield [Type::intersection(Type::object(\Iterator::class), $t), 'Countable&Iterator'];
        yield [Type::generic(Type::object(\Iterator::class), $t), 'Iterator<Countable>'];
        yield [Type::list($t), 'list<Countable>'];
        yield [Type::arrayShape(['a' => $t]), "array{'a': Countable}"];
        yield [Type::objectShape(['a' => $t]), "object{'a': Countable}"];
    }

    public function testMapReducesComposedTypes()
    {
        $t = Type::template('T');

        $this->assertEquals(Type::int(), Type::union($t, Type::int())->map(self::replaceTemplate(Type::int())));
        $this->assertEquals(Type::nullable(Type::int()), Type::union($t, Type::int())->map(self::replaceTemplate(Type::nullable(Type::int()))));
        $this->assertEquals(Type::mixed(), Type::union($t, Type::int())->map(self::replaceTemplate(Type::mixed())));

        $this->assertEquals(
            Type::object(\Iterator::class),
            Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class))->map(static fn (Type $t): Type => 'Stringable' === (string) $t ? Type::object(\Iterator::class) : $t),
        );
    }

    public function testMapThrowsOnInvalidComposedType()
    {
        $this->expectException(InvalidArgumentException::class);

        Type::union(Type::template('T'), Type::int())->map(self::replaceTemplate(Type::void()));
    }

    #[DataProvider('provideGetMismatches')]
    public function testGetMismatches(Type $type, mixed $value, array $expectedMismatches)
    {
        $this->assertEquals($expectedMismatches, $type->getMismatches($value));
        $this->assertSame([] === $expectedMismatches, $type->accepts($value));
    }

    public static function provideGetMismatches(): iterable
    {
        yield 'accepted' => [Type::int(), 1, []];
        yield 'builtin' => [Type::int(), '1', [new TypeMismatch('', Type::int(), 'string')]];
        yield 'object' => [Type::object(\Countable::class), new \stdClass(), [new TypeMismatch('', Type::object(\Countable::class), 'stdClass')]];
        yield 'nullable' => [Type::nullable(Type::int()), '1', [new TypeMismatch('', Type::nullable(Type::int()), 'string')]];
        yield 'union' => [Type::union(Type::int(), Type::string()), 1.5, [new TypeMismatch('', Type::union(Type::int(), Type::string()), 'float')]];

        yield 'list items' => [Type::list(Type::int()), [1, '2', 3, '4'], [new TypeMismatch('[1]', Type::int(), 'string'), new TypeMismatch('[3]', Type::int(), 'string')]];
        yield 'not a list' => [Type::list(Type::int()), [1 => 1], [new TypeMismatch('', Type::list(Type::int()), 'array')]];
        yield 'collection key' => [Type::dict(Type::int()), [1], [new TypeMismatch('', Type::dict(Type::int()), 'array')]];
        yield 'nested collections' => [Type::list(Type::list(Type::int())), [[1], [2, '3']], [new TypeMismatch('[1][1]', Type::int(), 'string')]];
        yield 'not an array' => [Type::iterable(Type::int()), new \ArrayIterator(['1']), [new TypeMismatch('', Type::iterable(Type::int()), 'ArrayIterator')]];

        $shape = Type::arrayShape(['foo' => Type::bool(), 'bar' => ['type' => Type::string(), 'optional' => true]]);
        yield 'array shape' => [$shape, 'foo', [new TypeMismatch('', $shape, 'string')]];
        yield 'array shape missing key' => [$shape, [], [new TypeMismatch('[foo]', Type::bool(), null)]];
        yield 'array shape value' => [$shape, ['foo' => true, 'bar' => 1], [new TypeMismatch('[bar]', Type::string(), 'int')]];
        yield 'array shape extra key' => [$shape, ['foo' => true, 'baz' => 1], [new TypeMismatch('[baz]', Type::never(), 'int')]];
        yield 'array shape in a list' => [Type::list($shape), [['foo' => true], ['foo' => 'x']], [new TypeMismatch('[1][foo]', Type::bool(), 'string')]];

        $unsealedShape = Type::arrayShape(['foo' => Type::bool()], false, Type::string(), Type::int());
        yield 'unsealed array shape extra value' => [$unsealedShape, ['foo' => true, 'baz' => '1'], [new TypeMismatch('[baz]', Type::int(), 'string')]];
        yield 'unsealed array shape extra key' => [$unsealedShape, ['foo' => true, 1 => 1], [new TypeMismatch('[1]', Type::never(), 'int')]];

        $objectShape = Type::objectShape(['foo' => Type::bool(), 'bar' => ['type' => Type::objectShape(['baz' => Type::int()]), 'optional' => true]]);
        yield 'object shape' => [$objectShape, [], [new TypeMismatch('', $objectShape, 'array')]];
        yield 'object shape missing property' => [$objectShape, new \stdClass(), [new TypeMismatch('foo', Type::bool(), null)]];
        yield 'object shape nested property' => [$objectShape, (object) ['foo' => true, 'bar' => (object) ['baz' => '1']], [new TypeMismatch('bar.baz', Type::int(), 'string')]];
        yield 'object shape extra property' => [$objectShape, (object) ['foo' => true, 'qux' => 1], [new TypeMismatch('qux', Type::never(), 'int')]];
        yield 'object shape in a list' => [Type::list($objectShape), [(object) ['foo' => 1]], [new TypeMismatch('[0].foo', Type::bool(), 'int')]];
        yield 'list in an object shape' => [Type::objectShape(['foo' => Type::list(Type::int())]), (object) ['foo' => ['1']], [new TypeMismatch('foo[0]', Type::int(), 'string')]];

        yield 'union member of the same kind' => [Type::nullable($shape), ['foo' => 1], [new TypeMismatch('[foo]', Type::bool(), 'int')]];
        $union = Type::union(Type::list(Type::int()), $shape);
        yield 'union members of the same kind' => [$union, [1, '2'], [new TypeMismatch('', $union, 'array')]];

        yield 'resolved type' => [
            TypeResolver::create()->resolve('array{data: list<array{id: string, shipment?: array{departure?: string|null, ...}|null, ...}>, ...}'),
            ['data' => [['id' => 'A-1', 'shipment' => ['departure' => 20260918]]]],
            [new TypeMismatch('[data][0][shipment][departure]', Type::nullable(Type::string()), 'int')],
        ];
    }

    private static function replaceTemplate(Type $replacement): \Closure
    {
        return static fn (Type $t): Type => $t instanceof TemplateType && 'T' === $t->getName() ? $replacement : $t;
    }
}
