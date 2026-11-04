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
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;

/**
 * @group integration
 */
class RedisTagAwareClusterAdapterTest extends RedisClusterAdapterTest
{
    use TagAwareTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skippedTests['testTagItemExpiry'] = 'Testing expiration slows down the test suite';
    }

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        if ('testClearWithPrefix' === $testMethod && \defined('Redis::SCAN_PREFIX')) {
            self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_PREFIX);
        }

        $this->assertInstanceOf(RedisClusterProxy::class, self::$redis);
        $adapter = new RedisTagAwareAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    public function testInvalidateTagsWithHashTagInPrefix()
    {
        $redis = RedisAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', getenv('REDIS_CLUSTER_HOSTS')).']', ['redis_cluster' => true]);
        $redis->setOption(\Redis::OPT_PREFIX, '{app}:');
        $pool = new RedisTagAwareAdapter($redis, str_replace('\\', '.', __CLASS__));

        $pool->save($pool->getItem('foo')->set('bar')->tag('tag'));

        $this->assertTrue($pool->invalidateTags(['tag']));
        $this->assertFalse($pool->getItem('foo')->isHit());
    }
}
