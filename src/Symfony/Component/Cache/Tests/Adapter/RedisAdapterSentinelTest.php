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

/**
 * @group integration
 */
class RedisAdapterSentinelTest extends AbstractRedisAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisSentinel::class)) {
            self::markTestSkipped('The RedisSentinel class is required.');
        }
        if (!$hosts = getenv('REDIS_SENTINEL_HOSTS')) {
            self::markTestSkipped('REDIS_SENTINEL_HOSTS env var is not defined.');
        }
        if (!$service = getenv('REDIS_SENTINEL_SERVICE')) {
            self::markTestSkipped('REDIS_SENTINEL_SERVICE env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['redis_sentinel' => $service]);
    }

    public function testInvalidDSNHasBothClusterAndSentinel()
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Cannot use both "redis_cluster" and "redis_sentinel" at the same time:');
        $dsn = 'redis:?host[redis1]&host[redis2]&host[redis3]&redis_cluster=1&redis_sentinel=mymaster';
        RedisAdapter::createConnection($dsn);
    }

    public function testExceptionMessageWhenFailingToRetrieveMasterInformation()
    {
        $hosts = getenv('REDIS_SENTINEL_HOSTS');
        $firstHost = explode(' ', $hosts)[0];
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Failed to retrieve master information from master name "invalid-masterset-name" and address "'.$firstHost.'".');
        AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['redis_sentinel' => 'invalid-masterset-name']);
    }
}
