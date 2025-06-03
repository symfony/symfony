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

use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @group integration
 */
class PredisRedisReplicationAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!$hosts = getenv('REDIS_REPLICATION_HOSTS')) {
            self::markTestSkipped('REDIS_REPLICATION_HOSTS env var is not defined.');
        }

        self::$redis = RedisAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).'][role]=master', ['replication' => 'predis', 'class' => \Predis\Client::class, 'prefix' => 'prefix_']);
    }
}
