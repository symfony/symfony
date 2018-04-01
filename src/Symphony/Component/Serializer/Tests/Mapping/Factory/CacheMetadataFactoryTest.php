<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Serializer\Mapping\ClassMetadata;
use Symphony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symphony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symphony\Component\Serializer\Tests\Fixtures\Dummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CacheMetadataFactoryTest extends TestCase
{
    public function testGetMetadataFor()
    {
        $metadata = new ClassMetadata(Dummy::class);

        $decorated = $this->getMockBuilder(ClassMetadataFactoryInterface::class)->getMock();
        $decorated
            ->expects($this->once())
            ->method('getMetadataFor')
            ->will($this->returnValue($metadata))
        ;

        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $this->assertEquals($metadata, $factory->getMetadataFor(Dummy::class));
        // The second call should retrieve the value from the cache
        $this->assertEquals($metadata, $factory->getMetadataFor(Dummy::class));
    }

    public function testHasMetadataFor()
    {
        $decorated = $this->getMockBuilder(ClassMetadataFactoryInterface::class)->getMock();
        $decorated
            ->expects($this->once())
            ->method('hasMetadataFor')
            ->will($this->returnValue(true))
        ;

        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $this->assertTrue($factory->hasMetadataFor(Dummy::class));
    }

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidClassThrowsException()
    {
        $decorated = $this->getMockBuilder(ClassMetadataFactoryInterface::class)->getMock();
        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $factory->getMetadataFor('Not\Exist');
    }
}
