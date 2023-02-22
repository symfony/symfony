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

use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\RedisClusterProxy;

/**
 * @group integration
 */
class RedisClusterAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisCluster::class)) {
            throw new SkippedTestSuiteError('The RedisCluster class is required.');
        }
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['lazy' => true, 'redis_cluster' => true]);
        self::$redis->setOption(\Redis::OPT_PREFIX, 'prefix_');
    }

    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        if ('testClearWithPrefix' === $testMethod && \defined('Redis::SCAN_PREFIX')) {
            self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_PREFIX);
        }

        $this->assertInstanceOf(RedisClusterProxy::class, self::$redis);
        $adapter = new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        RedisAdapter::createConnection($dsn);
    }

    public static function provideFailedCreateConnection(): array
    {
        return [
            ['redis://localhost:1234?redis_cluster=1'],
            ['redis://foo@localhost?redis_cluster=1'],
            ['redis://localhost/123?redis_cluster=1'],
        ];
    }
}
