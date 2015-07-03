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

use Symfony\Component\Serializer\Mapping\AttributeMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AttributeMetadataTest extends \PHPUnit_Framework_TestCase
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

    public function testMerge()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setAlias('alias');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals(array('a', 'b', 'c'), $attributeMetadata1->getGroups());
        $this->assertEquals('alias', $attributeMetadata1->getAlias());
    }

    public function testMergeWithExistingAlias()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->setAlias('a1');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->setAlias('a2');

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals('a1', $attributeMetadata1->getAlias());
    }
}
