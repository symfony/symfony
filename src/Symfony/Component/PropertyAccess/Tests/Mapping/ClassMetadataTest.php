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

use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $classMetadata = new ClassMetadata('name');
        $this->assertInstanceOf('Symfony\Component\PropertyAccess\Mapping\ClassMetadata', $classMetadata);
    }

    public function testAttributeMetadata()
    {
        $classMetadata = new ClassMetadata('c');

        $a1 = $this->getMock('Symfony\Component\PropertyAccess\Mapping\PropertyMetadata');
        $a1->method('getName')->willReturn('a1');

        $a2 = $this->getMock('Symfony\Component\PropertyAccess\Mapping\PropertyMetadata');
        $a2->method('getName')->willReturn('a2');

        $classMetadata->addPropertyMetadata($a1);
        $classMetadata->addPropertyMetadata($a2);

        $this->assertEquals(array('a1' => $a1, 'a2' => $a2), $classMetadata->getPropertyMetadataCollection());
    }

    public function testSerialize()
    {
        $classMetadata = new ClassMetadata('a');

        $a1 = $this->getMock('Symfony\Component\PropertyAccess\Mapping\PropertyMetadata');
        $a1->method('getName')->willReturn('b1');
        $a1->method('__sleep')->willReturn([]);

        $a2 = $this->getMock('Symfony\Component\PropertyAccess\Mapping\PropertyMetadata');
        $a2->method('getName')->willReturn('b2');
        $a2->method('__sleep')->willReturn([]);

        $classMetadata->addPropertyMetadata($a1);
        $classMetadata->addPropertyMetadata($a2);

        $serialized = serialize($classMetadata);
        $this->assertEquals($classMetadata, unserialize($serialized));
    }
}
