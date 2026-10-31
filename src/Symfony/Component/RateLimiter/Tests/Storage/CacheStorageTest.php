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
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class CacheStorageTest extends TestCase
{
    public function testFixedWindowPreservesLargeLimitsInCache()
    {
        if (\PHP_INT_SIZE < 8) {
            $this->markTestSkipped('Requires 64-bit integers.');
        }

        $limiter = (new RateLimiterFactory([
            'id' => 'test',
            'policy' => 'fixed_window',
            'limit' => 4294967296,
            'interval' => '1 minute',
        ], $this->createSerializedCacheStorage()))->create();

        $rateLimit = $limiter->consume(4294967296);
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertFalse($limiter->consume()->isAccepted());
    }

    public function testSlidingWindowPreservesLargeLimitsInCache()
    {
        if (\PHP_INT_SIZE < 8) {
            $this->markTestSkipped('Requires 64-bit integers.');
        }

        $limiter = (new RateLimiterFactory([
            'id' => 'test',
            'policy' => 'sliding_window',
            'limit' => 4294967296,
            'interval' => '1 minute',
        ], $this->createSerializedCacheStorage()))->create();

        $rateLimit = $limiter->consume(4294967296);
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(0, $rateLimit->getRemainingTokens());
        $this->assertFalse($limiter->consume()->isAccepted());
    }

    public function testSave()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->exactly(2))->method('expiresAfter')->with(10);

        $pool->method('getItem')->willReturnMap([
            [sha1('test'), $cacheItem],
        ]);
        $pool->expects($this->exactly(2))->method('save')->with($cacheItem);

        $window = new Window('test', 10, 20);
        $storage->save($window);

        $window = unserialize(serialize($window));
        $storage->save($window);
    }

    public function testFetchExistingState()
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $window = new Window('test', 10, 20);
        $cacheItem->method('get')->willReturn($window);
        $cacheItem->method('isHit')->willReturn(true);

        $pool->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertEquals($window, $storage->fetch('test'));
    }

    public function testFetchExistingJunk()
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);

        $cacheItem->method('get')->willReturn('junk');
        $cacheItem->method('isHit')->willReturn(true);

        $pool->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($storage->fetch('test'));
    }

    public function testFetchNonExistingState()
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $pool->method('getItem')->with(sha1('test'))->willReturn($cacheItem);

        $this->assertNull($storage->fetch('test'));
    }

    public function testDelete()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $storage = new CacheStorage($pool);

        $pool->expects($this->once())->method('deleteItem')->with(sha1('test'))->willReturn(true);

        $storage->delete('test');
    }

    private function createSerializedCacheStorage(): CacheStorage
    {
        $value = $data = null;
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('set')->willReturnCallback(static function ($newValue) use (&$value, $item) {
            $value = $newValue;

            return $item;
        });
        $item->method('get')->willReturnCallback(static function () use (&$data) {
            return null === $data ? null : unserialize($data);
        });

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->method('save')->willReturnCallback(static function () use (&$value, &$data) {
            $data = serialize($value);

            return true;
        });

        return new CacheStorage($pool);
    }
}
