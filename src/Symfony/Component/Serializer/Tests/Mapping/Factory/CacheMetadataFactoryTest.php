<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CacheMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMetadataFor()
    {
        $metadata = new ClassMetadata('Symfony\Component\Serializer\Tests\Fixtures\Dummy');

        $decorated = $this->getMock('Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface');
        $decorated
            ->expects($this->once())
            ->method('getMetadataFor')
            ->will($this->returnValue($metadata))
        ;

        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $this->assertEquals($metadata, $factory->getMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Dummy'));
        // The second call should retrieve the value from the cache
        $this->assertEquals($metadata, $factory->getMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Dummy'));
    }

    public function testHasMetadataFor()
    {
        $decorated = $this->getMock('Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface');
        $decorated
            ->expects($this->once())
            ->method('hasMetadataFor')
            ->will($this->returnValue(true))
        ;

        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $this->assertTrue($factory->hasMetadataFor('Symfony\Component\Serializer\Tests\Fixtures\Dummy'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidClassThrowsException()
    {
        $decorated = $this->getMock('Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface');
        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $factory->getMetadataFor('Not\Exist');
    }
}
