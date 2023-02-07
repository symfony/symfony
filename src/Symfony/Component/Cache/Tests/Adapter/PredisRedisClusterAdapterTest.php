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
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @group integration
 */
class PredisRedisClusterAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = RedisAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['class' => \Predis\Client::class, 'redis_cluster' => true, 'prefix' => 'prefix_']);
    }

    public static function tearDownAfterClass(): void
    {
        self::$redis = null;
    }
}
