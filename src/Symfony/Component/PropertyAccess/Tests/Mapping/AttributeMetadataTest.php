<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping;

use Symfony\Component\PropertyAccess\Mapping\AttributeMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AttributeMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\AttributeMetadataInterface', $attributeMetadata);
    }

    public function testGetName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertEquals('name', $attributeMetadata->getName());
    }

    public function testGetter()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setGetter('one');

        $this->assertEquals('one', $attributeMetadata->getGetter());
    }

    public function testSetter()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setSetter('one');

        $this->assertEquals('one', $attributeMetadata->getSetter());
    }

    public function testAdder()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setAdder('one');

        $this->assertEquals('one', $attributeMetadata->getAdder());
    }

    public function testRemover()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setRemover('one');

        $this->assertEquals('one', $attributeMetadata->getRemover());
    }

    public function testMerge()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->setGetter('a');
        $attributeMetadata1->setSetter('b');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->setGetter('c');
        $attributeMetadata2->setAdder('d');
        $attributeMetadata2->setRemover('e');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals('a', $attributeMetadata1->getGetter());
        $this->assertEquals('b', $attributeMetadata1->getSetter());
        $this->assertEquals('d', $attributeMetadata1->getAdder());
        $this->assertEquals('e', $attributeMetadata1->getRemover());
    }

    public function testSerialize()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->setGetter('a');
        $attributeMetadata->setSetter('b');
        $attributeMetadata->setAdder('c');
        $attributeMetadata->setRemover('d');

        $serialized = serialize($attributeMetadata);
        $this->assertEquals($attributeMetadata, unserialize($serialized));
    }
}
