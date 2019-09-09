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
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;

class RedisClusterAdapterTest extends AbstractRedisAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('RedisCluster')) {
            self::markTestSkipped('The RedisCluster class is required.');
        }
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['lazy' => true, 'redis_cluster' => true]);
    }

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        $this->assertInstanceOf(RedisClusterProxy::class, self::$redis);
        $adapter = new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection(string $dsn)
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Redis connection failed');
        RedisAdapter::createConnection($dsn);
    }

    public function provideFailedCreateConnection(): array
    {
        return [
            ['redis://localhost:1234?redis_cluster=1'],
            ['redis://foo@localhost?redis_cluster=1'],
            ['redis://localhost/123?redis_cluster=1'],
        ];
    }
}
