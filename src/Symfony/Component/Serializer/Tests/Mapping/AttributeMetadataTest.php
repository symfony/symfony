<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class AttributeMetadataTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertInstanceOf('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface', $attributeMetadata);
    }

    public function testGetName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertEquals('name', $attributeMetadata->getName());
    }

    public function testAccessor()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getMethodsAccessor());
        $this->assertNull($attributeMetadata->getMethodsMutator());

        $attributeMetadata->setMethodsAccessor('getter');
        $this->assertEquals('getter', $attributeMetadata->getMethodsAccessor());

        $attributeMetadata->setMethodsMutator('setter');
        $this->assertEquals('setter', $attributeMetadata->getMethodsMutator());
    }

    public function testExclude()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getExclude());

        $attributeMetadata->setExclude(true);
        $this->assertTrue($attributeMetadata->getExclude());

        $attributeMetadata->setExclude(false);
        $this->assertFalse($attributeMetadata->getExclude());
    }

    public function testExpose()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getExpose());

        $attributeMetadata->setExpose(true);
        $this->assertTrue($attributeMetadata->getExpose());

        $attributeMetadata->setExpose(false);
        $this->assertFalse($attributeMetadata->getExpose());
    }

    public function testGroups()
    {
        $attributeMetadata = new AttributeMetadata('group');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');

        $this->assertEquals(array('a', 'b'), $attributeMetadata->getGroups());
    }

    public function testMaxDepth()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getMaxDepth());

        $attributeMetadata->setMaxDepth(69);
        $this->assertEquals(69, $attributeMetadata->getMaxDepth());
    }

    public function testReadOnly()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getReadOnly());

        $attributeMetadata->setReadOnly(true);
        $this->assertTrue($attributeMetadata->getReadOnly());

        $attributeMetadata->setReadOnly(false);
        $this->assertFalse($attributeMetadata->getReadOnly());
    }

    public function testSerializedName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getSerializedName());

        $serializedName = 'foobar';
        $attributeMetadata->setSerializedName($serializedName);
        $this->assertEquals($serializedName, $attributeMetadata->getSerializedName());
    }
    public function testType()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertNull($attributeMetadata->getType());

        $type = 'foobar';
        $attributeMetadata->setType($type);
        $this->assertEquals($type, $attributeMetadata->getType());
    }

    public function testMerge()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setMethodsAccessor('getter');
        $attributeMetadata2->setMethodsMutator('setter');
        $attributeMetadata2->setExclude(true);
        $attributeMetadata2->setExpose(false);
        $attributeMetadata2->setMaxDepth(2);
        $attributeMetadata2->setReadOnly(true);
        $attributeMetadata2->setSerializedName('serialized_name');
        $attributeMetadata2->setType('type');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals(array('a', 'b', 'c'), $attributeMetadata1->getGroups());
        $this->assertEquals('getter', $attributeMetadata1->getMethodsAccessor());
        $this->assertEquals('setter', $attributeMetadata1->getMethodsMutator());
        $this->assertEquals(true, $attributeMetadata1->getExclude());
        $this->assertEquals(false, $attributeMetadata1->getExpose());
        $this->assertEquals(2, $attributeMetadata1->getMaxDepth());
        $this->assertEquals(true, $attributeMetadata1->getReadOnly());
        $this->assertEquals('serialized_name', $attributeMetadata1->getSerializedName());
        $this->assertEquals('type', $attributeMetadata1->getType());
    }

    /**
     * Exsisting values of $attributeMetadata1 should not be overwritten.
     */
    public function testMergeNoOverwrite()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');
        $attributeMetadata1->setMethodsAccessor('getter');
        $attributeMetadata1->setMethodsMutator('setter');
        $attributeMetadata1->setExclude(true);
        $attributeMetadata1->setExpose(false);
        $attributeMetadata1->setMaxDepth(2);
        $attributeMetadata1->setReadOnly(true);
        $attributeMetadata1->setSerializedName('serialized_name');
        $attributeMetadata1->setType('type');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setMethodsAccessor('getter2');
        $attributeMetadata2->setMethodsMutator('setter2');
        $attributeMetadata2->setExclude(false);
        $attributeMetadata2->setExpose(true);
        $attributeMetadata2->setMaxDepth(3);
        $attributeMetadata2->setReadOnly(false);
        $attributeMetadata2->setSerializedName('serialized_name2');
        $attributeMetadata2->setType('type2');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals(array('a', 'b', 'c'), $attributeMetadata1->getGroups());
        $this->assertEquals('getter', $attributeMetadata1->getMethodsAccessor());
        $this->assertEquals('setter', $attributeMetadata1->getMethodsMutator());
        $this->assertEquals(true, $attributeMetadata1->getExclude());
        $this->assertEquals(false, $attributeMetadata1->getExpose());
        $this->assertEquals(2, $attributeMetadata1->getMaxDepth());
        $this->assertEquals(true, $attributeMetadata1->getReadOnly());
        $this->assertEquals('serialized_name', $attributeMetadata1->getSerializedName());
        $this->assertEquals('type', $attributeMetadata1->getType());
    }

    public function testSerialize()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');
        $attributeMetadata->setMaxDepth(3);

        $serialized = serialize($attributeMetadata);
        $this->assertEquals($attributeMetadata, unserialize($serialized));
    }
}
