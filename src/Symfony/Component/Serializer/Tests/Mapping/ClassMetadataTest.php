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
use Symfony\Component\Serializer\Mapping\ClassMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ClassMetadataTest extends TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadata('name');
        $this->assertInstanceOf('Symfony\Component\Serializer\Mapping\ClassMetadataInterface', $classMetadata);
    }

    public function testAttributeMetadata()
    {
        $classMetadata = new ClassMetadata('c');

        $a1 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $a1->method('getName')->willReturn('a1');

        $a2 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $a2->method('getName')->willReturn('a2');

        $classMetadata->addAttributeMetadata($a1);
        $classMetadata->addAttributeMetadata($a2);

        $this->assertEquals(array('a1' => $a1, 'a2' => $a2), $classMetadata->getAttributesMetadata());
    }

    public function testMerge()
    {
        $classMetadata1 = new ClassMetadata('c1');
        $classMetadata2 = new ClassMetadata('c2');

        $ac1 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $ac1->method('getName')->willReturn('a1');
        $ac1->method('getGroups')->willReturn(array('a', 'b'));

        $ac2 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $ac2->method('getName')->willReturn('a1');
        $ac2->method('getGroups')->willReturn(array('b', 'c'));

        $classMetadata1->addAttributeMetadata($ac1);
        $classMetadata2->addAttributeMetadata($ac2);

        $classMetadata1->merge($classMetadata2);

        $ac1->method('getGroups')->willReturn('a', 'b', 'c');

        $this->assertEquals(array('a1' => $ac1), $classMetadata2->getAttributesMetadata());
    }

    public function testExclusionPolicy()
    {
        $policy = 'foo';
        $classMetadata = new ClassMetadata('a');
        $this->assertNull($classMetadata->getExclusionPolicy());

        $classMetadata->setExclusionPolicy($policy);
        $this->assertEquals($policy, $classMetadata->getExclusionPolicy());
    }

    public function testReadOnly()
    {
        $classMetadata = new ClassMetadata('a');
        $this->assertNull($classMetadata->getReadOnly());

        $classMetadata->setReadOnly(true);
        $this->assertTrue($classMetadata->getReadOnly());

        $classMetadata->setReadOnly(false);
        $this->assertFalse($classMetadata->getReadOnly());
    }

    public function testSerialize()
    {
        $classMetadata = new ClassMetadata('a');

        $classMetadata->setExclusionPolicy('foo');
        $classMetadata->setReadOnly(true);

        $a1 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $a1->method('getName')->willReturn('b1');

        $a2 = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\AttributeMetadataInterface')->getMock();
        $a2->method('getName')->willReturn('b2');

        $classMetadata->addAttributeMetadata($a1);
        $classMetadata->addAttributeMetadata($a2);

        $serialized = serialize($classMetadata);
        $this->assertEquals($classMetadata, unserialize($serialized));
    }
}
