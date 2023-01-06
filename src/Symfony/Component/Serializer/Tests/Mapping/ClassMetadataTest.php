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
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataTest extends TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadata('name');
        $this->assertInstanceOf(ClassMetadataInterface::class, $classMetadata);
    }

    public function testAttributeMetadata()
    {
        $classMetadata = new ClassMetadata('c');

        $a1 = new AttributeMetadata('a1');
        $a2 = new AttributeMetadata('a2');

        $classMetadata->addAttributeMetadata($a1);
        $classMetadata->addAttributeMetadata($a2);

        $this->assertEquals(['a1' => $a1, 'a2' => $a2], $classMetadata->getAttributesMetadata());
    }

    public function testMerge()
    {
        $classMetadata1 = new ClassMetadata('c1');
        $classMetadata2 = new ClassMetadata('c2');

        $ac1 = new AttributeMetadata('a1');
        $ac1->addGroup('a');
        $ac1->addGroup('b');
        $ac2 = new AttributeMetadata('a1');
        $ac2->addGroup('b');
        $ac2->addGroup('c');

        $classMetadata1->addAttributeMetadata($ac1);
        $classMetadata2->addAttributeMetadata($ac2);

        $classMetadata1->merge($classMetadata2);

        $this->assertSame(['a', 'b', 'c'], $ac1->getGroups());
        $this->assertEquals(['a1' => $ac1], $classMetadata1->getAttributesMetadata());
    }

    public function testSerialize()
    {
        $classMetadata = new ClassMetadata('a');

        $a1 = new AttributeMetadata('b1');
        $a2 = new AttributeMetadata('b2');

        $classMetadata->addAttributeMetadata($a1);
        $classMetadata->addAttributeMetadata($a2);

        $serialized = serialize($classMetadata);
        $this->assertEquals($classMetadata, unserialize($serialized));
    }
}
