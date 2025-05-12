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
use Relay\Cluster as RelayCluster;
use Relay\Relay;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\RelayClusterProxy;

/**
 * @requires extension relay
 *
 * @group integration
 */
class RelayClusterAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(RelayCluster::class)) {
            self::markTestSkipped('The Relay\Cluster class is required.');
        }
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['lazy' => true, 'redis_cluster' => true, 'class' => RelayCluster::class]);
        self::$redis->setOption(Relay::OPT_PREFIX, 'prefix_');
    }

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        $this->assertInstanceOf(RelayClusterProxy::class, self::$redis);
        $adapter = new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relay cluster connection failed:');
        RedisAdapter::createConnection($dsn);
    }

    public static function provideFailedCreateConnection(): array
    {
        return [
            ['redis://localhost:1234?redis_cluster=1&class=Relay\Cluster'],
            ['redis://foo@localhost?redis_cluster=1&class=Relay\Cluster'],
            ['redis://localhost/123?redis_cluster=1&class=Relay\Cluster'],
        ];
    }
}
