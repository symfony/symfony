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
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorConfiguration;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;

class ClassDiscriminatorConfigurationTest extends TestCase
{
    public function testItConfiguresAClassWithMultipleMappings()
    {
        $resolver = new ClassDiscriminatorConfiguration();
        $resolver->setClassMapping(AbstractDummy::class, $abstractDummyMapping = new ClassDiscriminatorMapping('type', array(
            'first' => AbstractDummyFirstChild::class,
            'second' => AbstractDummySecondChild::class,
        )));

        $this->assertEquals($abstractDummyMapping, $resolver->getMappingForClass(AbstractDummy::class));
        $this->assertNull($resolver->getMappingForClass(Dummy::class));

        $this->assertEquals($abstractDummyMapping, $resolver->getMappingForMappedObject(AbstractDummy::class));
        $this->assertEquals($abstractDummyMapping, $resolver->getMappingForMappedObject(AbstractDummyFirstChild::class));
        $this->assertEquals($abstractDummyMapping, $resolver->getMappingForMappedObject(AbstractDummySecondChild::class));

        $this->assertEquals('first', $resolver->getTypeForMappedObject(new AbstractDummyFirstChild()));
        $this->assertEquals('second', $resolver->getTypeForMappedObject(new AbstractDummySecondChild()));
        $this->assertEquals('first', $resolver->getTypeForMappedObject(AbstractDummyFirstChild::class));
        $this->assertEquals('second', $resolver->getTypeForMappedObject(AbstractDummySecondChild::class));
        $this->assertNull($resolver->getTypeForMappedObject(Dummy::class));
    }
}
