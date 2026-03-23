<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\InMemoryLRUSimpleCache;
use Symfony\Component\Cache\MemoryUsageCalculator;
use Symfony\Component\Cache\Tests\Adapter\AdapterTestCase;
use Symfony\Component\Clock\MockClock;

#[Group('time-sensitive')]
final class InMemoryLRUSimpleCacheAdapterTest extends AdapterTestCase
{
    private static ClockInterface $clock;

    private static MemoryUsageCalculator $memoryUsageCalculator;

    protected $skippedTests = [
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which InMemoryLRUCache is not.',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Assumes a shared cache which InMemoryLRUCache is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which InMemoryLRUCache is not.',
        'testGetMetadata' => 'InMemoryLRUCache does not keep metadata.',
        'testNotUnserializable' => 'InMemoryLRUCache does not support serialization.',
        'testClearPrefix' => 'InMemoryLRUSimpleCache cannot clear by prefix',
        'testDefaultLifeTime' => 'InMemoryLRUSimpleCache does not allow configuring a default lifetime.',
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testPrune' => 'Testing prune slows down the test suite',
    ];

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        // load class before test to be sure that MemoryUsageCalculator is reachable
        class_exists(InMemoryLRUSimpleCache::class);

        // always calculate 0 for memory usage to avoid flaky tests
        self::$memoryUsageCalculator = new class implements MemoryUsageCalculator {
            public function calculate(): int
            {
                return 0;
            }
        };
        self::$clock = new MockClock();

        parent::setUpBeforeClass();
    }

    public function createCachePool(): CacheItemPoolInterface
    {
        return new Psr16Adapter(new InMemoryLRUSimpleCache(self::$clock, self::$memoryUsageCalculator));
    }

    protected function isPruned(CacheItemPoolInterface $cache, string $name): bool
    {
        $lruCache = \Closure::bind(static fn ($instance) => $instance->pool, null, Psr16Adapter::class)($cache);

        return $lruCache instanceof InMemoryLRUSimpleCache && !$lruCache->has($name);
    }
}
