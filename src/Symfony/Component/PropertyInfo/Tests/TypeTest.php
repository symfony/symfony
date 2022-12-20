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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TypeTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testLegacyConstruct()
    {
        $this->expectDeprecation('Since symfony/property-info 5.3: The "Symfony\Component\PropertyInfo\Type::getCollectionKeyType()" method is deprecated, use "getCollectionKeyTypes()" instead.');
        $this->expectDeprecation('Since symfony/property-info 5.3: The "Symfony\Component\PropertyInfo\Type::getCollectionValueType()" method is deprecated, use "getCollectionValueTypes()" instead.');

        $type = new Type('object', true, 'ArrayObject', true, new Type('int'), new Type('string'));

        self::assertEquals(Type::BUILTIN_TYPE_OBJECT, $type->getBuiltinType());
        self::assertTrue($type->isNullable());
        self::assertEquals('ArrayObject', $type->getClassName());
        self::assertTrue($type->isCollection());

        $collectionKeyType = $type->getCollectionKeyType();
        self::assertInstanceOf(Type::class, $collectionKeyType);
        self::assertEquals(Type::BUILTIN_TYPE_INT, $collectionKeyType->getBuiltinType());

        $collectionValueType = $type->getCollectionValueType();
        self::assertInstanceOf(Type::class, $collectionValueType);
        self::assertEquals(Type::BUILTIN_TYPE_STRING, $collectionValueType->getBuiltinType());
    }

    public function testConstruct()
    {
        $type = new Type('object', true, 'ArrayObject', true, new Type('int'), new Type('string'));

        self::assertEquals(Type::BUILTIN_TYPE_OBJECT, $type->getBuiltinType());
        self::assertTrue($type->isNullable());
        self::assertEquals('ArrayObject', $type->getClassName());
        self::assertTrue($type->isCollection());

        $collectionKeyTypes = $type->getCollectionKeyTypes();
        self::assertIsArray($collectionKeyTypes);
        self::assertContainsOnlyInstancesOf('Symfony\Component\PropertyInfo\Type', $collectionKeyTypes);
        self::assertEquals(Type::BUILTIN_TYPE_INT, $collectionKeyTypes[0]->getBuiltinType());

        $collectionValueTypes = $type->getCollectionValueTypes();
        self::assertIsArray($collectionValueTypes);
        self::assertContainsOnlyInstancesOf('Symfony\Component\PropertyInfo\Type', $collectionValueTypes);
        self::assertEquals(Type::BUILTIN_TYPE_STRING, $collectionValueTypes[0]->getBuiltinType());
    }

    public function testIterable()
    {
        $type = new Type('iterable');
        self::assertSame('iterable', $type->getBuiltinType());
    }

    public function testInvalidType()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('"foo" is not a valid PHP type.');
        new Type('foo');
    }

    public function testArrayCollection()
    {
        $type = new Type('array', false, null, true, [new Type('int'), new Type('string')], [new Type('object', false, \ArrayObject::class, true), new Type('array', false, null, true)]);

        self::assertEquals(Type::BUILTIN_TYPE_ARRAY, $type->getBuiltinType());
        self::assertFalse($type->isNullable());
        self::assertTrue($type->isCollection());

        [$firstKeyType, $secondKeyType] = $type->getCollectionKeyTypes();
        self::assertEquals(Type::BUILTIN_TYPE_INT, $firstKeyType->getBuiltinType());
        self::assertFalse($firstKeyType->isNullable());
        self::assertFalse($firstKeyType->isCollection());
        self::assertEquals(Type::BUILTIN_TYPE_STRING, $secondKeyType->getBuiltinType());
        self::assertFalse($secondKeyType->isNullable());
        self::assertFalse($secondKeyType->isCollection());

        [$firstValueType, $secondValueType] = $type->getCollectionValueTypes();
        self::assertEquals(Type::BUILTIN_TYPE_OBJECT, $firstValueType->getBuiltinType());
        self::assertEquals(\ArrayObject::class, $firstValueType->getClassName());
        self::assertFalse($firstValueType->isNullable());
        self::assertTrue($firstValueType->isCollection());
        self::assertEquals(Type::BUILTIN_TYPE_ARRAY, $secondValueType->getBuiltinType());
        self::assertFalse($secondValueType->isNullable());
        self::assertTrue($firstValueType->isCollection());
    }

    public function testInvalidCollectionArgument()
    {
        self::expectException(\TypeError::class);
        self::expectExceptionMessage('"Symfony\Component\PropertyInfo\Type::validateCollectionArgument()": Argument #5 ($collectionKeyType) must be of type "Symfony\Component\PropertyInfo\Type[]", "Symfony\Component\PropertyInfo\Type" or "null", "stdClass" given.');

        new Type('array', false, null, true, new \stdClass(), [new Type('string')]);
    }

    public function testInvalidCollectionValueArgument()
    {
        self::expectException(\TypeError::class);
        self::expectExceptionMessage('"Symfony\Component\PropertyInfo\Type::validateCollectionArgument()": Argument #5 ($collectionKeyType) must be of type "Symfony\Component\PropertyInfo\Type[]", "Symfony\Component\PropertyInfo\Type" or "null", array value "array" given.');

        new Type('array', false, null, true, [new \stdClass()], [new Type('string')]);
    }
}
