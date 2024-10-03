<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheTrait;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CacheTraitTest extends TestCase
{
    public function testSave()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')
            ->willReturn($item);
        $item->method('isHit')
            ->willReturn(false);

        $item->expects($this->once())
            ->method('set')
            ->with('computed data');

        $cache = $this->getMockBuilder(TestPool::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMock();
        $cache->expects($this->once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects($this->once())
            ->method('save');

        $callback = fn (CacheItemInterface $item) => 'computed data';

        $cache->get('key', $callback);
    }

    public function testNoCallbackCallOnHit()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(true);

        $item->expects($this->never())
            ->method('set');

        $cache = $this->getMockBuilder(TestPool::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects($this->never())
            ->method('save');

        $callback = function (CacheItemInterface $item) {
            $this->fail('This code should never be reached');
        };

        $cache->get('key', $callback);
    }

    public function testRecomputeOnBetaInf()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')
            ->willReturn($item);
        $item->method('isHit')
            // We want to recompute even if it is a hit
            ->willReturn(true);

        $item->expects($this->once())
            ->method('set')
            ->with('computed data');

        $cache = $this->getMockBuilder(TestPool::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMock();

        $cache->expects($this->once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects($this->once())
            ->method('save');

        $callback = fn (CacheItemInterface $item) => 'computed data';

        $cache->get('key', $callback, \INF);
    }

    public function testExceptionOnNegativeBeta()
    {
        $cache = $this->getMockBuilder(TestPool::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMock();

        $callback = fn (CacheItemInterface $item) => 'computed data';

        $this->expectException(\InvalidArgumentException::class);
        $cache->get('key', $callback, -2);
    }
}

class TestPool implements CacheItemPoolInterface
{
    use CacheTrait;

    public function hasItem($key): bool
    {
    }

    public function deleteItem($key): bool
    {
    }

    public function deleteItems(array $keys = []): bool
    {
    }

    public function getItem($key): CacheItemInterface
    {
    }

    public function getItems(array $key = []): iterable
    {
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
    }

    public function save(CacheItemInterface $item): bool
    {
    }

    public function commit(): bool
    {
    }

    public function clear(): bool
    {
    }
}
