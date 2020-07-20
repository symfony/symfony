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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;

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
            ->willReturn($metadata)
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
            ->willReturn(true)
        ;

        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $this->assertTrue($factory->hasMetadataFor(Dummy::class));
    }

    public function testInvalidClassThrowsException()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $decorated = $this->getMockBuilder(ClassMetadataFactoryInterface::class)->getMock();
        $factory = new CacheClassMetadataFactory($decorated, new ArrayAdapter());

        $factory->getMetadataFor('Not\Exist');
    }
}
