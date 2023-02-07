<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * @group integration
 */
class ProxyAdapterAndRedisAdapterTest extends AbstractRedisAdapterTestCase
{
    protected $skippedTests = [
        'testPrune' => 'RedisAdapter does not implement PruneableInterface.',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$redis = AbstractAdapter::createConnection('redis://'.getenv('REDIS_HOST'));
    }

    public function createCachePool($defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        return new ProxyAdapter(new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), 100), 'ProxyNS', $defaultLifetime);
    }

    public function testSaveItemPermanently()
    {
        $setCacheItemExpiry = \Closure::bind(
            static function (CacheItem $item, $expiry) {
                $item->expiry = $expiry;

                return $item;
            },
            null,
            CacheItem::class
        );

        $cache = $this->createCachePool(1);
        $cache->clear();
        $value = rand();
        $item = $cache->getItem('foo');
        $setCacheItemExpiry($item, 0);
        $cache->save($item->set($value));
        $item = $cache->getItem('bar');
        $setCacheItemExpiry($item, 0.0);
        $cache->save($item->set($value));
        $item = $cache->getItem('baz');
        $cache->save($item->set($value));

        $this->assertSame($value, $this->cache->getItem('foo')->get());
        $this->assertSame($value, $this->cache->getItem('bar')->get());
        $this->assertSame($value, $this->cache->getItem('baz')->get());

        sleep(1);
        $this->assertSame($value, $this->cache->getItem('foo')->get());
        $this->assertSame($value, $this->cache->getItem('bar')->get());
        $this->assertFalse($this->cache->getItem('baz')->isHit());
    }
}
