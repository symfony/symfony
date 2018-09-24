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

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;

class RedisClusterAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        if (!class_exists('RedisCluster')) {
            self::markTestSkipped('The RedisCluster class is required.');
        }
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis://'.str_replace(' ', ',', $hosts), array('lazy' => true, 'cluster' => 'server'));
    }

    public function createCachePool($defaultLifetime = 0)
    {
        $this->assertInstanceOf(RedisClusterProxy::class, self::$redis);
        $adapter = new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);

        return $adapter;
    }

    public function testCreateConnection()
    {
        $hosts = str_replace(' ', ',', getenv('REDIS_CLUSTER_HOSTS'));

        $redis = RedisAdapter::createConnection('redis://'.$hosts.'?cluster=server');
        $this->assertInstanceOf(\RedisCluster::class, $redis);
    }

    /**
     * @dataProvider provideFailedCreateConnection
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Redis connection failed
     */
    public function testFailedCreateConnection($dsn)
    {
        RedisAdapter::createConnection($dsn);
    }

    public function provideFailedCreateConnection()
    {
        return array(
            array('redis://localhost:1234?cluster=server'),
            array('redis://foo@localhost?cluster=server'),
            array('redis://localhost/123?cluster=server'),
        );
    }
}
