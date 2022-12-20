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
        $item = self::createMock(CacheItemInterface::class);
        $item->method('set')
            ->willReturn($item);
        $item->method('isHit')
            ->willReturn(false);

        $item->expects(self::once())
            ->method('set')
            ->with('computed data');

        $cache = self::getMockBuilder(TestPool::class)
            ->setMethods(['getItem', 'save'])
            ->getMock();
        $cache->expects(self::once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects(self::once())
            ->method('save');

        $callback = function (CacheItemInterface $item) {
            return 'computed data';
        };

        $cache->get('key', $callback);
    }

    public function testNoCallbackCallOnHit()
    {
        $item = self::createMock(CacheItemInterface::class);
        $item->method('isHit')
            ->willReturn(true);

        $item->expects(self::never())
            ->method('set');

        $cache = self::getMockBuilder(TestPool::class)
            ->setMethods(['getItem', 'save'])
            ->getMock();

        $cache->expects(self::once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects(self::never())
            ->method('save');

        $callback = function (CacheItemInterface $item) {
            self::assertTrue(false, 'This code should never be reached');
        };

        $cache->get('key', $callback);
    }

    public function testRecomputeOnBetaInf()
    {
        $item = self::createMock(CacheItemInterface::class);
        $item->method('set')
            ->willReturn($item);
        $item->method('isHit')
            // We want to recompute even if it is a hit
            ->willReturn(true);

        $item->expects(self::once())
            ->method('set')
            ->with('computed data');

        $cache = self::getMockBuilder(TestPool::class)
            ->setMethods(['getItem', 'save'])
            ->getMock();

        $cache->expects(self::once())
            ->method('getItem')
            ->with('key')
            ->willReturn($item);
        $cache->expects(self::once())
            ->method('save');

        $callback = function (CacheItemInterface $item) {
            return 'computed data';
        };

        $cache->get('key', $callback, \INF);
    }

    public function testExceptionOnNegativeBeta()
    {
        $cache = self::getMockBuilder(TestPool::class)
            ->setMethods(['getItem', 'save'])
            ->getMock();

        $callback = function (CacheItemInterface $item) {
            return 'computed data';
        };

        self::expectException(\InvalidArgumentException::class);
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
