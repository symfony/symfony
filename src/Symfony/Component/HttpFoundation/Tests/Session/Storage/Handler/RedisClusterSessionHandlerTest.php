<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\SkippedTestSuiteError;

/**
 * @group integration
 */
class RedisClusterSessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\RedisCluster::class)) {
            throw new SkippedTestSuiteError('The RedisCluster class is required.');
        }

        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }
    }

    /**
     * @return \RedisCluster
     */
    protected function createRedisClient(string $host): object
    {
        return new \RedisCluster(null, explode(' ', getenv('REDIS_CLUSTER_HOSTS')));
    }
}
