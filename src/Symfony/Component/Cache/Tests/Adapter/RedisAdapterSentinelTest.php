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
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @group integration
 */
class RedisAdapterSentinelTest extends AbstractRedisAdapterTestCase
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

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['redis_sentinel' => $service, 'prefix' => 'prefix_']);
    }

    public function testInvalidDSNHasBothClusterAndSentinel()
    {
        $dsn = 'redis:?host[redis1]&host[redis2]&host[redis3]&redis_cluster=1&redis_sentinel=mymaster';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use both "redis_cluster" and "redis_sentinel" at the same time.');

        RedisAdapter::createConnection($dsn);
    }

    public function testExceptionMessageWhenFailingToRetrieveMasterInformation()
    {
        $hosts = getenv('REDIS_SENTINEL_HOSTS');
        $dsn = 'redis:?host['.str_replace(' ', ']&host[', $hosts).']';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to retrieve master information from sentinel "invalid-masterset-name".');
        AbstractAdapter::createConnection($dsn, ['redis_sentinel' => 'invalid-masterset-name']);
    }
}
