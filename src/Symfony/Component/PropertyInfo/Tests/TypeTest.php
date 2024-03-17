<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\TypeInfo\Type as TypeInfoType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @group legacy
 */
class TypeTest extends TestCase
{
    public function testConstruct()
    {
        $type = new Type('object', true, 'ArrayObject', true, new Type('int'), new Type('string'));

        $this->assertEquals(Type::BUILTIN_TYPE_OBJECT, $type->getBuiltinType());
        $this->assertTrue($type->isNullable());
        $this->assertEquals('ArrayObject', $type->getClassName());
        $this->assertTrue($type->isCollection());

        $collectionKeyTypes = $type->getCollectionKeyTypes();
        $this->assertIsArray($collectionKeyTypes);
        $this->assertContainsOnlyInstancesOf('Symfony\Component\PropertyInfo\Type', $collectionKeyTypes);
        $this->assertEquals(Type::BUILTIN_TYPE_INT, $collectionKeyTypes[0]->getBuiltinType());

        $collectionValueTypes = $type->getCollectionValueTypes();
        $this->assertIsArray($collectionValueTypes);
        $this->assertContainsOnlyInstancesOf('Symfony\Component\PropertyInfo\Type', $collectionValueTypes);
        $this->assertEquals(Type::BUILTIN_TYPE_STRING, $collectionValueTypes[0]->getBuiltinType());
    }

    public function testIterable()
    {
        $type = new Type('iterable');
        $this->assertSame('iterable', $type->getBuiltinType());
    }

    public function testInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"foo" is not a valid PHP type.');
        new Type('foo');
    }

    public function testArrayCollection()
    {
        $type = new Type('array', false, null, true, [new Type('int'), new Type('string')], [new Type('object', false, \ArrayObject::class, true), new Type('array', false, null, true)]);

        $this->assertEquals(Type::BUILTIN_TYPE_ARRAY, $type->getBuiltinType());
        $this->assertFalse($type->isNullable());
        $this->assertTrue($type->isCollection());

        [$firstKeyType, $secondKeyType] = $type->getCollectionKeyTypes();
        $this->assertEquals(Type::BUILTIN_TYPE_INT, $firstKeyType->getBuiltinType());
        $this->assertFalse($firstKeyType->isNullable());
        $this->assertFalse($firstKeyType->isCollection());
        $this->assertEquals(Type::BUILTIN_TYPE_STRING, $secondKeyType->getBuiltinType());
        $this->assertFalse($secondKeyType->isNullable());
        $this->assertFalse($secondKeyType->isCollection());

        [$firstValueType, $secondValueType] = $type->getCollectionValueTypes();
        $this->assertEquals(Type::BUILTIN_TYPE_OBJECT, $firstValueType->getBuiltinType());
        $this->assertEquals(\ArrayObject::class, $firstValueType->getClassName());
        $this->assertFalse($firstValueType->isNullable());
        $this->assertTrue($firstValueType->isCollection());
        $this->assertEquals(Type::BUILTIN_TYPE_ARRAY, $secondValueType->getBuiltinType());
        $this->assertFalse($secondValueType->isNullable());
        $this->assertTrue($firstValueType->isCollection());
    }

    public function testInvalidCollectionValueArgument()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('"Symfony\Component\PropertyInfo\Type::validateCollectionArgument()": Argument #5 ($collectionKeyType) must be of type "Symfony\Component\PropertyInfo\Type[]", "Symfony\Component\PropertyInfo\Type" or "null", array value "array" given.');

        new Type('array', false, null, true, [new \stdClass()], [new Type('string')]);
    }

    /**
     * @dataProvider createProperInternalTypeDataProvider
     *
     * @param list<TypeInfoType> $types
     * @param list<Type>         $legacyTypes
     */
    public function testCreateProperInternalTypes(array $types, array $legacyTypes)
    {
        $this->assertEquals($types, array_map(fn (Type $t): TypeInfoType => $t->internalType, $legacyTypes));
    }

    /**
     * @return iterable<array{0: list<TypeInfoType>, 1: list<LegacyType>}>
     */
    public function createProperInternalTypeDataProvider(): iterable
    {
        yield [[TypeInfoType::null()], [new Type('null')]];
        yield [[TypeInfoType::int()], [new Type('int')]];
        yield [[TypeInfoType::object(\stdClass::class)], [new Type('object', false, \stdClass::class)]];
        yield [
            [TypeInfoType::generic(TypeInfoType::object('Foo'), TypeInfoType::string(), TypeInfoType::int())],
            [new Type('object', false, 'Foo', false, [new Type('string')], new Type('int'))],
        ];
        yield [[TypeInfoType::nullable(TypeInfoType::int())], [new Type('int', true)]];
        yield [[TypeInfoType::int(), TypeInfoType::string()], [new Type('int'), new Type('string')]];

        $type = TypeInfoType::collection(TypeInfoType::builtin(TypeIdentifier::ARRAY), TypeInfoType::int(), TypeInfoType::string());
        yield [[$type], [new Type('array', false, null, true, [new Type('string')], new Type('int'))]];
    }
}
