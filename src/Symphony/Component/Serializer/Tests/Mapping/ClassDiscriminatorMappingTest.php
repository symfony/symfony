<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symphony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild;
use Symphony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorMappingTest extends TestCase
{
    public function testGetClass()
    {
        $mapping = new ClassDiscriminatorMapping('type', array(
            'first' => AbstractDummyFirstChild::class,
        ));

        $this->assertEquals(AbstractDummyFirstChild::class, $mapping->getClassForType('first'));
        $this->assertEquals(null, $mapping->getClassForType('second'));
    }

    public function testMappedObjectType()
    {
        $mapping = new ClassDiscriminatorMapping('type', array(
            'first' => AbstractDummyFirstChild::class,
        ));

        $this->assertEquals('first', $mapping->getMappedObjectType(new AbstractDummyFirstChild()));
        $this->assertEquals(null, $mapping->getMappedObjectType(new AbstractDummySecondChild()));
    }
}
