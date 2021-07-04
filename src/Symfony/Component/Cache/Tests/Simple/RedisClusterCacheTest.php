<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use PHPUnit\Framework\SkippedTestSuiteError;

/**
 * @group legacy
 * @group integration
 */
class RedisClusterCacheTest extends AbstractRedisCacheTest
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisCluster::class)) {
            throw new SkippedTestSuiteError('The RedisCluster class is required.');
        }
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = new \RedisCluster(null, explode(' ', $hosts));
    }
}
