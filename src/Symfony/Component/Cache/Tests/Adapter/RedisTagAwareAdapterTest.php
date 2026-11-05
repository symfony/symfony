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
use Symfony\Component\Cache\Tests\Fixtures\StrictKeysRedis;
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

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        if ('testClearWithPrefix' === $testMethod && \defined('Redis::SCAN_PREFIX')) {
            self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_PREFIX);
        }

        $this->assertInstanceOf(RedisProxy::class, self::$redis);
        $adapter = new RedisTagAwareAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    public function testInvalidateTagsOnlyAccessesDeclaredKeys()
    {
        $redis = RedisAdapter::createConnection('redis://'.getenv('REDIS_HOST'), ['class' => StrictKeysRedis::class]);
        $redis->setOption(\Redis::OPT_PREFIX, 'strict_');
        $pool = new RedisTagAwareAdapter($redis, str_replace('\\', '.', __CLASS__));
        $pool->clear();

        $item = $pool->getItem('foo');
        $pool->save($item->set('bar')->tag('tag'));

        $this->assertTrue($pool->invalidateTags(['tag']));
        $this->assertFalse($pool->getItem('foo')->isHit());
    }
}
