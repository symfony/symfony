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

class PredisRedisClusterAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = RedisAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['class' => \Predis\Client::class, 'redis_cluster' => true]);
    }

    public static function tearDownAfterClass()
    {
        self::$redis = null;
    }
}
