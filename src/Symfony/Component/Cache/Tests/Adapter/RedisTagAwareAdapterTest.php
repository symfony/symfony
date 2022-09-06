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
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Traits\RedisProxy;

/**
 * @group integration
 */
class RedisTagAwareAdapterTest extends RedisAdapterTest
{
    use TagAwareTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skippedTests['testTagItemExpiry'] = 'Testing expiration slows down the test suite';
    }

    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null, $tagLifetime = null): CacheItemPoolInterface
    {
        if ('testClearWithPrefix' === $testMethod && \defined('Redis::SCAN_PREFIX')) {
            self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_PREFIX);
        }

        $this->assertInstanceOf(RedisProxy::class, self::$redis);
        $adapter = new RedisTagAwareAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime, null, $tagLifetime);

        return $adapter;
    }

    public function testTagExpiry()
    {
        $pool = $this->createCachePool(10, null, true);

        $item = $pool->getItem('tag_item_expiry');
        $item->tag(['tag_item_expiry_tag']);
        $item->expiresAfter(5);

        $pool->save($item);

        sleep(7);

        $redis = self::$redis;

        $keys = $redis->keys('*tag_item_expiry*');
        $this->assertCount(0, $keys);
    }

    public function testTagExpirySeparateLifetime()
    {
        $pool = $this->createCachePool(10, null, 10);

        $item = $pool->getItem('tag_expiry');
        $item->tag(['tag_expiry_tag']);
        $item->expiresAfter(5);

        $pool->save($item);

        sleep(7);

        $redis = self::$redis;

        $keys = $redis->keys('*tag_expiry*');
        $this->assertCount(1, $keys);

        sleep(5);

        $keys = $redis->keys('*tag_expiry*');
        $this->assertCount(0, $keys);
    }
}
