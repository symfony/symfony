<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
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

        $collectionKeyType = $type->getCollectionKeyType();
        $this->assertInstanceOf('Symphony\Component\PropertyInfo\Type', $collectionKeyType);
        $this->assertEquals(Type::BUILTIN_TYPE_INT, $collectionKeyType->getBuiltinType());

        $collectionValueType = $type->getCollectionValueType();
        $this->assertInstanceOf('Symphony\Component\PropertyInfo\Type', $collectionValueType);
        $this->assertEquals(Type::BUILTIN_TYPE_STRING, $collectionValueType->getBuiltinType());
    }

    public function testIterable()
    {
        $type = new Type('iterable');
        $this->assertSame('iterable', $type->getBuiltinType());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "foo" is not a valid PHP type.
     */
    public function testInvalidType()
    {
        new Type('foo');
    }
}
