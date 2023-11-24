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

/**
 * @requires extension redis
 */
class RedisTraitTest extends TestCase
{
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

    public function testUrlDecodeParameters()
    {
        if (!getenv('REDIS_AUTHENTICATED_HOST')) {
            self::markTestSkipped('REDIS_AUTHENTICATED_HOST env var is not defined.');
        }

        $mock = self::getObjectForTrait(RedisTrait::class);
        $connection = $mock::createConnection('redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST'));

        self::assertInstanceOf(\Redis::class, $connection);
        self::assertSame('p@ssword', $connection->getAuth());
    }

    public static function provideCreateConnection(): array
    {
        $hosts = array_map(fn ($host) => sprintf('host[%s]', $host), explode(' ', getenv('REDIS_CLUSTER_HOSTS')));

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
