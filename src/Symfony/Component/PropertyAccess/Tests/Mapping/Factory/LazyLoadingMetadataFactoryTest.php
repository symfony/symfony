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

        $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
    }

    public function testMergeParentMetadata()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::CLASSNAME);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
            self::CLASSNAME => new PropertyMetadata(self::CLASSNAME),
        );

        $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
    }

    public function testWriteMetadataToCache()
    {
        $cache = $this->getMock('Psr\Cache\CacheItemPoolInterface');
        $factory = new LazyLoadingMetadataFactory(new TestLoader(), $cache);

        $properties = array(
            self::PARENTCLASS => new PropertyMetadata(self::PARENTCLASS),
        );

        $cacheItem = $this->getMock('Psr\Cache\CacheItemInterface');

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->equalTo($this->escapeClassName(self::PARENTCLASS)))
            ->will($this->returnValue($cacheItem));

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(false));

        $cacheItem->expects($this->once())
            ->method('set')
            ->will($this->returnCallback(function ($metadata) use ($properties) {
                  $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
              }));

        $cache->expects($this->once())
            ->method('save')
            ->with($this->equalTo($cacheItem))
            ->will($this->returnValue(true));

        $metadata = $factory->getMetadataFor(self::PARENTCLASS);

        $this->assertEquals(self::PARENTCLASS, $metadata->getName());
        $this->assertEquals($properties, $metadata->getPropertyMetadataCollection());
    }

    public function testReadMetadataFromCache()
    {
        $loader = $this->getMock('Symfony\Component\PropertyAccess\Mapping\Loader\LoaderInterface');
        $cache = $this->getMock('Psr\Cache\CacheItemPoolInterface');
        $factory = new LazyLoadingMetadataFactory($loader, $cache);

        $metadata = new ClassMetadata(self::PARENTCLASS);
        $metadata->addPropertyMetadata(new PropertyMetadata());

        $loader->expects($this->never())
               ->method('loadClassMetadata');

        $cacheItem = $this->getMock('Psr\Cache\CacheItemInterface');

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->equalTo($this->escapeClassName(self::PARENTCLASS)))
            ->will($this->returnValue($cacheItem));

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(true));

        $cacheItem->expects($this->once())
            ->method('get')
            ->will($this->returnValue($metadata));

        $cacheItem->expects($this->never())
            ->method('set');

        $cache->expects($this->never())
            ->method('save');

        $this->assertEquals($metadata, $factory->getMetadataFor(self::PARENTCLASS));
    }

    /**
     * Replaces backslashes by dots in a class name.
     *
     * @param string $class
     *
     * @return string
     */
    private function escapeClassName($class)
    {
        return str_replace('\\', '.', $class);
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyMetadata(new PropertyMetadata($metadata->getName()));
    }
}
