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
    private $pool;
    private $storage;

    protected function setUp(): void
    {
        $this->pool = $this->createMock(CacheItemPoolInterface::class);
        $this->storage = new CacheStorage($this->pool);
    }

    public function testSave()
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->exactly(2))->method('expiresAfter')->with(10);

        $this->pool->expects($this->any())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);
        $this->pool->expects($this->exactly(2))->method('save')->with($cacheItem);

        $window = new Window('test', 10, 20);
        $this->storage->save($window);

        $window = unserialize(serialize($window));
        $this->storage->save($window);
    }

    public function testFetchExistingState()
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $window = new Window('test', 10, 20);
        $cacheItem->expects($this->any())->method('get')->willReturn($window);
        $cacheItem->expects($this->any())->method('isHit')->willReturn(true);

        $this->pool->expects($this->any())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertEquals($window, $this->storage->fetch('test'));
    }

    public function testFetchExistingJunk()
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cacheItem->expects($this->any())->method('get')->willReturn('junk');
        $cacheItem->expects($this->any())->method('isHit')->willReturn(true);

        $this->pool->expects($this->any())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($this->storage->fetch('test'));
    }

    public function testFetchNonExistingState()
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->any())->method('isHit')->willReturn(false);

        $this->pool->expects($this->any())->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($this->storage->fetch('test'));
    }

    public function testDelete()
    {
        $this->pool->expects($this->once())->method('deleteItem')->with(sha1('test'))->willReturn(true);

        $this->storage->delete('test');
    }
}
