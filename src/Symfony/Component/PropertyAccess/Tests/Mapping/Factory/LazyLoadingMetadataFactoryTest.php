<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Mapping\Factory;

use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface;
use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;

class LazyLoadingMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\PropertyAccess\Tests\Fixtures\Dummy';
    const PARENTCLASS = 'Symfony\Component\PropertyAccess\Tests\Fixtures\DummyParent';

    public function testLoadClassMetadata()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::PARENTCLASS);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
        );

        $this->assertEquals($properties, $metadata->getPropertiesMetadata());
    }

    public function testMergeParentMetadata()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::CLASSNAME);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
            self::CLASSNAME => new PropertyMetadata(self::CLASSNAME),
        );

        $this->assertEquals($properties, $metadata->getPropertiesMetadata());
    }

    public function testWriteMetadataToCache()
    {
        $cache = $this->getMock('Symfony\Component\PropertyAccess\Mapping\Cache\CacheInterface');
        $factory = new LazyLoadingMetadataFactory(new TestLoader(), $cache);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
        );

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->once())
              ->method('read')
              ->with($this->equalTo(self::PARENTCLASS))
              ->will($this->returnValue(false));
        $cache->expects($this->once())
              ->method('write')
              ->will($this->returnCallback(function ($metadata) use ($properties) {
                  $this->assertEquals($properties, $metadata->getPropertiesMetadata());
              }));

        $metadata = $factory->getMetadataFor(self::PARENTCLASS);

        $this->assertEquals(self::PARENTCLASS, $metadata->getName());
        $this->assertEquals($properties, $metadata->getPropertiesMetadata());
    }

    public function testReadMetadataFromCache()
    {
        $loader = $this->getMock('Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface');
        $cache = $this->getMock('Symfony\Component\PropertyAccess\Mapping\Cache\CacheInterface');
        $factory = new LazyLoadingMetadataFactory($loader, $cache);

        $metadata = new ClassMetadata(self::PARENTCLASS);
        $metadata->addPropertyMetadata(new PropertyMetadata());

        $loader->expects($this->never())
               ->method('loadClassMetadata');

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->once())
              ->method('read')
              ->will($this->returnValue($metadata));

        $this->assertEquals($metadata, $factory->getMetadataFor(self::PARENTCLASS));
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyMetadata(new PropertyMetadata($metadata->getName()));
    }
}
