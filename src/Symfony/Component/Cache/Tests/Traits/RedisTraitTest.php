<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Traits\RedisTrait;

class RedisTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }
    }

    /**
     * @dataProvider provideCreateConnection
     */
    public function testCreateConnection(string $dsn, string $expectedClass)
    {
        if (!class_exists($expectedClass)) {
            throw new SkippedTestSuiteError(sprintf('The "%s" class is required.', $expectedClass));
        }
        if (!getenv('REDIS_CLUSTER_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        $mock = self::getObjectForTrait(RedisTrait::class);
        $connection = $mock::createConnection($dsn);

        self::assertInstanceOf($expectedClass, $connection);
    }

    public static function provideCreateConnection(): array
    {
        $hosts = array_map(function ($host) { return sprintf('host[%s]', $host); }, explode(' ', getenv('REDIS_CLUSTER_HOSTS')));

        return [
            [
                sprintf('redis:?%s&redis_cluster=1', $hosts[0]),
                'RedisCluster',
            ],
            [
                sprintf('redis:?%s&redis_cluster=true', $hosts[0]),
                'RedisCluster',
            ],
            [
                sprintf('redis:?%s', $hosts[0]),
                'Redis',
            ],
            [
                'dsn' => sprintf('redis:?%s', implode('&', \array_slice($hosts, 0, 2))),
                'RedisArray',
            ],
        ];
    }
}
