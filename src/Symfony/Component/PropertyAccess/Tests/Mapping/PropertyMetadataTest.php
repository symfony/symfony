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

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyMetadataTest extends TestCase
{
    public function testInterface()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\PropertyMetadata', $propertyMetadata);
    }

    public function testGetName()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $this->assertEquals('name', $propertyMetadata->getName());
    }

    public function testGetter()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $propertyMetadata->setGetter('one');

        $this->assertEquals('one', $propertyMetadata->getGetter());
    }

    public function testSetter()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $propertyMetadata->setSetter('one');

        $this->assertEquals('one', $propertyMetadata->getSetter());
    }

    public function testAdder()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $propertyMetadata->setAdder('one');

        $this->assertEquals('one', $propertyMetadata->getAdder());
    }

    public function testRemover()
    {
        $propertyMetadata = new PropertyMetadata('name');
        $propertyMetadata->setRemover('one');

        $this->assertEquals('one', $propertyMetadata->getRemover());
    }

    public function testMerge()
    {
        $propertyMetadata1 = new PropertyMetadata('a1');
        $propertyMetadata1->setGetter('a');
        $propertyMetadata1->setSetter('b');

        $propertyMetadata2 = new PropertyMetadata('a2');
        $propertyMetadata2->setGetter('c');
        $propertyMetadata2->setAdder('d');
        $propertyMetadata2->setRemover('e');

        $propertyMetadata1->merge($propertyMetadata2);

        $this->assertEquals('a', $propertyMetadata1->getGetter());
        $this->assertEquals('b', $propertyMetadata1->getSetter());
        $this->assertEquals('d', $propertyMetadata1->getAdder());
        $this->assertEquals('e', $propertyMetadata1->getRemover());
    }

    public function testSerialize()
    {
        $propertyMetadata = new PropertyMetadata('attribute');
        $propertyMetadata->setGetter('a');
        $propertyMetadata->setSetter('b');
        $propertyMetadata->setAdder('c');
        $propertyMetadata->setRemover('d');

        $serialized = serialize($propertyMetadata);
        $this->assertEquals($propertyMetadata, unserialize($serialized));
    }
}
