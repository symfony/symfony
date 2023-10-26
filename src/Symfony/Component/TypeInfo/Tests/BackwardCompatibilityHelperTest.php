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
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\BackwardCompatibilityHelper;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @group legacy
 */
class BackwardCompatibilityHelperTest extends TestCase
{
    /**
     * @dataProvider convertTypeToLegacyTypesDataProvider
     *
     * @param list<LegacyType>|null $legacyTypes
     */
    public function testConvertTypeToLegacyTypes(?array $legacyTypes, ?Type $type, bool $keepNullType = true)
    {
        $this->assertEquals($legacyTypes, BackwardCompatibilityHelper::convertTypeToLegacyTypes($type, $keepNullType));
    }

    /**
     * @return iterable<array{0: list<LegacyType>|null, 1: ?Type, 2?: bool}>
     */
    public function convertTypeToLegacyTypesDataProvider(): iterable
    {
        yield [null, null];
        yield [null, Type::mixed()];
        yield [null, Type::never()];
        yield [null, Type::union(Type::int(), Type::intersection(Type::string(), Type::bool()))];
        yield [null, Type::intersection(Type::int(), Type::union(Type::string(), Type::bool()))];
        yield [null, Type::null(), false];
        yield [[new LegacyType('null')], Type::null()];
        yield [[new LegacyType('null')], Type::void()];
        yield [[new LegacyType('int')], Type::int()];
        yield [[new LegacyType('object', false, \stdClass::class)], Type::object(\stdClass::class)];
        yield [
            [new LegacyType('object', false, \Traversable::class, true, null, new LegacyType('int'))],
            Type::generic(Type::object(\Traversable::class), Type::int()),
        ];
        yield [
            [new LegacyType('array', false, null, true, new LegacyType('int'), new LegacyType('string'))],
            Type::generic(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::string()),
        ];
        yield [
            [new LegacyType('array', false, null, true, new LegacyType('int'), new LegacyType('string'))],
            Type::collection(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::int()),
        ];
        yield [[new LegacyType('int', true)], Type::nullable(Type::int())];
        yield [[new LegacyType('int'), new LegacyType('string')], Type::union(Type::int(), Type::string())];
        yield [
            [new LegacyType('int', true), new LegacyType('string', true)],
            Type::union(Type::int(), Type::string(), Type::null()),
        ];
        yield [[new LegacyType('int'), new LegacyType('string')], Type::intersection(Type::int(), Type::string())];

        $type = Type::int();
        $type->isCollection = true;
        yield [[new LegacyType('int', false, null, true)], $type];
    }

    /**
     * @dataProvider convertLegacyTypesToTypeDataProvider
     *
     * @param list<LegacyType>|null $legacyTypes
     */
    public function testConvertLegacyTypesToType(?Type $type, ?array $legacyTypes)
    {
        $this->assertEquals($type, BackwardCompatibilityHelper::convertLegacyTypesToType($legacyTypes));
    }

    /**
     * @return iterable<array{): ?Type, 1: list<LegacyType>|null}>
     */
    public function convertLegacyTypesToTypeDataProvider(): iterable
    {
        yield [null, null];
        yield [Type::null(), [new LegacyType('null')]];
        yield [Type::int(), [new LegacyType('int')]];
        yield [Type::object(\stdClass::class), [new LegacyType('object', false, \stdClass::class)]];
        yield [
            Type::generic(Type::object('Foo'), Type::string(), Type::int()),
            [new LegacyType('object', false, 'Foo', false, [new LegacyType('string')], new LegacyType('int'))],
        ];
        yield [Type::nullable(Type::int()), [new LegacyType('int', true)]];
        yield [Type::union(Type::int(), Type::string()), [new LegacyType('int'), new LegacyType('string')]];
        yield [
            Type::union(Type::int(), Type::string(), Type::null()),
            [new LegacyType('int', true), new LegacyType('string', true)],
        ];

        $type = Type::collection(Type::builtin(TypeIdentifier::ARRAY), Type::int(), Type::string());
        $type->isCollection = true;
        $type->getType()->isCollection = true;
        $type->getType()->getType()->isCollection = true;
        yield [$type, [new LegacyType('array', false, null, true, [new LegacyType('string')], new LegacyType('int'))]];
    }
}
