<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Cache;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Mapping\Cache\CacheInterface;
use Symphony\Component\Validator\Mapping\ClassMetadata;

abstract class AbstractCacheTest extends TestCase
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    public function testWrite()
    {
        $meta = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('Foo\\Bar'));

        $this->cache->write($meta);

        $this->assertInstanceOf(
            ClassMetadata::class,
            $this->cache->read('Foo\\Bar'),
            'write() stores metadata'
        );
    }

    public function testHas()
    {
        $meta = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('Foo\\Bar'));

        $this->assertFalse($this->cache->has('Foo\\Bar'), 'has() returns false when there is no entry');

        $this->cache->write($meta);
        $this->assertTrue($this->cache->has('Foo\\Bar'), 'has() returns true when the is an entry');
    }

    public function testRead()
    {
        $meta = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getClassName'))
            ->getMock();

        $meta->expects($this->once())
            ->method('getClassName')
            ->will($this->returnValue('Foo\\Bar'));

        $this->assertFalse($this->cache->read('Foo\\Bar'), 'read() returns false when there is no entry');

        $this->cache->write($meta);

        $this->assertInstanceOf(ClassMetadata::class, $this->cache->read('Foo\\Bar'), 'read() returns metadata');
    }
}
