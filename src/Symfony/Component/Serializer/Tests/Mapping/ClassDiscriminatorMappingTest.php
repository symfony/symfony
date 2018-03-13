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
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild;

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
