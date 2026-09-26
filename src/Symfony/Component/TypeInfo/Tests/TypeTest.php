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

        $this->assertEquals(
            Type::object(\Iterator::class),
            Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class))->map(static fn (Type $t): Type => 'Stringable' === (string) $t ? Type::object(\Iterator::class) : $t),
        );
    }

    public function testMapThrowsOnInvalidComposedType()
    {
        $this->expectException(InvalidArgumentException::class);

        Type::union(Type::template('T'), Type::int())->map(self::replaceTemplate(Type::mixed()));
    }

    private static function replaceTemplate(Type $replacement): \Closure
    {
        return static fn (Type $t): Type => $t instanceof TemplateType && 'T' === $t->getName() ? $replacement : $t;
    }
}
