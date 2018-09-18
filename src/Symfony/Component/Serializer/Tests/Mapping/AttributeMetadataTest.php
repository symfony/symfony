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
        $attributeMetadata->setMaxDepth(69);

        $this->assertEquals(69, $attributeMetadata->getMaxDepth());
    }

    public function testSerializedName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setSerializedName('serialized_name');

        $this->assertEquals('serialized_name', $attributeMetadata->getSerializedName());
    }

    public function testMerge()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setMaxDepth(2);
        $attributeMetadata2->setSerializedName('a3');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals(array('a', 'b', 'c'), $attributeMetadata1->getGroups());
        $this->assertEquals(2, $attributeMetadata1->getMaxDepth());
        $this->assertEquals('a3', $attributeMetadata1->getSerializedName());
    }

    public function testSerialize()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');
        $attributeMetadata->setMaxDepth(3);
        $attributeMetadata->setSerializedName('serialized_name');

        $serialized = serialize($attributeMetadata);
        $this->assertEquals($attributeMetadata, unserialize($serialized));
    }
}
