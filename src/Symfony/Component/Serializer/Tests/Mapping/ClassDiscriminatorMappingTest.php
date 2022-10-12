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
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyThirdChild;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorMappingTest extends TestCase
{
    public function testGetClass()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
        ]);

        $this->assertEquals(AbstractDummyFirstChild::class, $mapping->getClassForType('first'));
        $this->assertNull($mapping->getClassForType('second'));
    }

    public function testMappedObjectType()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
            'third' => AbstractDummyThirdChild::class,
        ]);

        $this->assertEquals('first', $mapping->getMappedObjectType(AbstractDummyFirstChild::class));
        $this->assertEquals('first', $mapping->getMappedObjectType(new AbstractDummyFirstChild()));
        $this->assertNull($mapping->getMappedObjectType(new AbstractDummySecondChild()));
        $this->assertSame('third', $mapping->getMappedObjectType(new AbstractDummyThirdChild()));
    }
}
