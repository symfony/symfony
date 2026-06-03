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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\RedisTrait;

#[RequiresPhpExtension('redis')]
class RedisTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped(getenv('REDIS_HOST').': '.$e->getMessage());
        }
    }

    #[DataProvider('provideCreateConnection')]
    public function testCreateConnection(string $dsn, string $expectedClass)
    {
        if (!class_exists($expectedClass)) {
            self::markTestSkipped(\sprintf('The "%s" class is required.', $expectedClass));
        }
        if (!getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        $mock = new class {
            use RedisTrait;
        };
        $connection = $mock::createConnection($dsn);

        self::assertInstanceOf($expectedClass, $connection);
    }

    public function testUrlDecodeParameters()
    {
        if (!getenv('REDIS_AUTHENTICATED_HOST')) {
            self::markTestSkipped('REDIS_AUTHENTICATED_HOST env var is not defined.');
        }

        $mock = new class {
            use RedisTrait;
        };
        $connection = $mock::createConnection('redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST'));

        self::assertInstanceOf(\Redis::class, $connection);
        self::assertSame('p@ssword', $connection->getAuth());
    }

    public static function provideCreateConnection(): array
    {
        $hosts = array_map(static fn ($host) => \sprintf('host[%s]', $host), explode(' ', getenv('REDIS_CLUSTER_HOSTS') ?: ''));

        return [
            [
                \sprintf('redis:?%s&redis_cluster=1', $hosts[0]),
                'RedisCluster',
            ],
            [
                \sprintf('redis:?%s&redis_cluster=true', $hosts[0]),
                'RedisCluster',
            ],
            [
                \sprintf('redis:?%s', $hosts[0]),
                'Redis',
            ],
            [
                \sprintf('redis:?%s', implode('&', \array_slice($hosts, 0, 2))),
                'RedisArray',
            ],
        ];
    }

    /**
     * Due to a bug in phpredis, the persistent connection will keep its last selected database. So when reusing
     * a persistent connection, the database has to be re-selected, too.
     *
     * @see https://github.com/phpredis/phpredis/issues/1920
     */
    #[Group('integration')]
    public function testPconnectSelectsCorrectDatabase()
    {
        if (!\ini_get('redis.pconnect.pooling_enabled')) {
            self::markTestSkipped('The bug only occurs when pooling is enabled.');
        }

        // Limit the connection pool size to 1:
        if (false === $prevPoolSize = ini_set('redis.pconnect.connection_limit', 1)) {
            self::markTestSkipped('Unable to set pool size');
        }

        try {
            $mock = new class {
                use RedisTrait;
            };

            $dsn = 'redis://'.getenv('REDIS_HOST');

            $cacheKey = 'testPconnectSelectsCorrectDatabase';
            $cacheValueOnDb1 = 'I should only be on database 1';

            // First connect to database 1 and set a value there so we can identify this database:
            $db1 = $mock::createConnection($dsn, ['dbindex' => 1, 'persistent' => 1]);
            self::assertInstanceOf(\Redis::class, $db1);
            self::assertSame(1, $db1->getDbNum());
            $db1->set($cacheKey, $cacheValueOnDb1);
            self::assertSame($cacheValueOnDb1, $db1->get($cacheKey));

            // Unset the connection - do not use `close()` or we will lose the persistent connection:
            unset($db1);

            // Now connect to database 0 and see that we do not actually ended up on database 1 by checking the value:
            $db0 = $mock::createConnection($dsn, ['dbindex' => 0, 'persistent' => 1]);
            self::assertInstanceOf(\Redis::class, $db0);
            self::assertSame(0, $db0->getDbNum()); // Redis is lying here! We could actually be on any database!
            self::assertNotSame($cacheValueOnDb1, $db0->get($cacheKey)); // This value should not exist if we are actually on db 0
        } finally {
            ini_set('redis.pconnect.connection_limit', $prevPoolSize);
        }
    }

    #[DataProvider('provideDbIndexDsnParameter')]
    public function testDbIndexDsnParameter(string $dsn, int $expectedDb)
    {
        if (!getenv('REDIS_AUTHENTICATED_HOST')) {
            self::markTestSkipped('REDIS_AUTHENTICATED_HOST env var is not defined.');
        }

        $mock = new class {
            use RedisTrait;
        };
        $connection = $mock::createConnection($dsn);
        self::assertSame($expectedDb, $connection->getDbNum());
    }

    public static function provideDbIndexDsnParameter(): array
    {
        return [
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST'),
                0,
            ],
            [
                'redis:?host['.getenv('REDIS_HOST').']',
                0,
            ],
            [
                'redis:?host['.getenv('REDIS_HOST').']&dbindex=1',
                1,
            ],
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST').'?dbindex=2',
                2,
            ],
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST').'/4',
                4,
            ],
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST').'/?dbindex=5',
                5,
            ],
        ];
    }

    #[DataProvider('provideInvalidDbIndexDsnParameter')]
    public function testInvalidDbIndexDsnParameter(string $dsn)
    {
        if (!getenv('REDIS_AUTHENTICATED_HOST')) {
            self::markTestSkipped('REDIS_AUTHENTICATED_HOST env var is not defined.');
        }
        $this->expectException(InvalidArgumentException::class);

        $mock = new class {
            use RedisTrait;
        };
        $mock::createConnection($dsn);
    }

    public static function provideInvalidDbIndexDsnParameter(): array
    {
        return [
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST').'/abc',
            ],
            [
                'redis://:p%40ssword@'.getenv('REDIS_AUTHENTICATED_HOST').'/3?dbindex=6',
            ],
        ];
    }

    #[DataProvider('providePredisMasterAuthResolution')]
    public function testPredisMasterAuthResolution(string $dsn, array $options, string|array|null $expectedMasterAuth)
    {
        $predisClass = $this->createPredisCaptureClass();

        $mock = new class {
            use RedisTrait;
        };

        $mock::createConnection($dsn, ['class' => $predisClass] + $options);

        $this->assertAuthMatchesExpected($expectedMasterAuth, $predisClass::$captured['options']['parameters'] ?? []);
    }

    public static function providePredisMasterAuthResolution(): \Generator
    {
        yield 'userinfo user+pass' => [
            'redis://user:pass@localhost',
            [],
            ['user', 'pass'],
        ];

        yield 'userinfo with @ + query auth array' => [
            'redis://user@pass@localhost?auth[]=otheruser&auth[]=otherpass',
            [],
            ['otheruser', 'otherpass'],
        ];

        yield 'query auth array' => [
            'redis://localhost?auth[]=user&auth[]=pass',
            [],
            ['user', 'pass'],
        ];

        yield 'options auth array' => [
            'redis://localhost',
            ['auth' => ['user', 'pass']],
            ['user', 'pass'],
        ];

        yield 'query auth beats options auth' => [
            'redis://localhost?auth[]=query-user&auth[]=query-pass',
            ['auth' => ['opt-user', 'opt-pass']],
            ['query-user', 'query-pass'],
        ];
    }

    #[DataProvider('providePredisSentinelAuthResolution')]
    public function testPredisSentinelAuthResolution(string $dsn, array $options, string|array|null $expectedMasterAuth, string|array|null $expectedSentinelAuth)
    {
        $predisClass = $this->createPredisCaptureClass();

        $mock = new class {
            use RedisTrait;
        };

        $mock::createConnection($dsn, ['class' => $predisClass] + $options);

        $this->assertAuthMatchesExpected($expectedMasterAuth, $predisClass::$captured['options']['parameters'] ?? []);
        $this->assertAuthMatchesExpected($expectedSentinelAuth, $predisClass::$captured['parameters'][0] ?? []);
    }

    public static function providePredisSentinelAuthResolution(): \Generator
    {
        yield 'sentinel query auth, master userinfo' => [
            'redis://master-user:master-pass@localhost?redis_sentinel=mymaster&auth[]=sentinel-user&auth[]=sentinel-pass',
            [],
            ['master-user', 'master-pass'],
            ['sentinel-user', 'sentinel-pass'],
        ];

        yield 'sentinel options auth when query missing' => [
            'redis://master-pass@localhost?redis_sentinel=mymaster',
            ['auth' => ['sentinel-user', 'sentinel-pass']],
            'master-pass',
            ['sentinel-user', 'sentinel-pass'],
        ];

        yield 'sentinel query auth beats options auth' => [
            'redis://master-pass@localhost?redis_sentinel=mymaster&auth[]=query-user&auth[]=query-pass',
            ['auth' => ['opt-user', 'opt-pass']],
            'master-pass',
            ['query-user', 'query-pass'],
        ];
    }

    private function assertAuthMatchesExpected(string|array|null $expectedAuth, array $parameters): void
    {
        if (null === $expectedAuth) {
            self::assertArrayNotHasKey('username', $parameters);
            self::assertArrayNotHasKey('password', $parameters);

            return;
        }

        if (\is_array($expectedAuth)) {
            self::assertSame($expectedAuth[0], $parameters['username'] ?? null);
            self::assertSame($expectedAuth[1], $parameters['password'] ?? null);

            return;
        }

        self::assertArrayNotHasKey('username', $parameters);
        self::assertSame($expectedAuth, $parameters['password'] ?? null);
    }

    private function createPredisCaptureClass(): string
    {
        $predisClass = new class extends \Predis\Client {
            public static array $captured = [];
            private object $connection;

            public function __construct($parameters = null, $options = null)
            {
                self::$captured = [
                    'parameters' => $parameters,
                    'options' => $options,
                ];
                $this->connection = new class {
                    public function setSentinelTimeout(float $timeout): void
                    {
                    }
                };
            }

            public function getConnection()
            {
                return $this->connection;
            }
        };

        return $predisClass::class;
    }
}
