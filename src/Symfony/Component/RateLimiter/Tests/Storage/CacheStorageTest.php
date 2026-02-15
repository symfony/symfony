<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\Policy\Window;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class CacheStorageTest extends TestCase
{
    public function testSave()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->exactly(2))->method('expiresAfter')->with(10);

        $pool->expects($this->any())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);
        $pool->expects($this->exactly(2))->method('save')->with($cacheItem);

        $window = new Window('test', 10, 20);
        $storage->save($window);

        $window = unserialize(serialize($window));
        $storage->save($window);
    }

    public function testFetchExistingState()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $window = new Window('test', 10, 20);
        $cacheItem->method('get')->willReturn($window);
        $cacheItem->method('isHit')->willReturn(true);

        $pool->expects($this->once())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertEquals($window, $storage->fetch('test'));
    }

    public function testFetchExistingJunk()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);

        $cacheItem->method('get')->willReturn('junk');
        $cacheItem->method('isHit')->willReturn(true);

        $pool->expects($this->once())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($storage->fetch('test'));
    }

    public function testFetchNonExistingState()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $pool->expects($this->once())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($storage->fetch('test'));
    }

    public function testDelete()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $pool->expects($this->once())->method('deleteItem')->with(sha1('test'))->willReturn(true);

        $storage->delete('test');
    }
}
