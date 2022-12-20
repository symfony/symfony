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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\RedisProxy;

/**
 * @group integration
 */
class RedisAdapterTest extends AbstractRedisAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$redis = AbstractAdapter::createConnection('redis://'.getenv('REDIS_HOST'), ['lazy' => true]);
    }

    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        if ('testClearWithPrefix' === $testMethod && \defined('Redis::SCAN_PREFIX')) {
            self::$redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_PREFIX);
        }

        $adapter = parent::createCachePool($defaultLifetime, $testMethod);
        self::assertInstanceOf(RedisProxy::class, self::$redis);

        return $adapter;
    }

    public function testCreateHostConnection()
    {
        $redis = RedisAdapter::createConnection('redis:?host[h1]&host[h2]&host[/foo:]');
        self::assertInstanceOf(\RedisArray::class, $redis);
        self::assertSame(['h1:6379', 'h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $this->doTestCreateConnection(getenv('REDIS_HOST'));
    }

    public function testCreateSocketConnection()
    {
        if (!getenv('REDIS_SOCKET') || !file_exists(getenv('REDIS_SOCKET'))) {
            self::markTestSkipped('Redis socket not found');
        }

        $this->doTestCreateConnection(getenv('REDIS_SOCKET'));
    }

    private function doTestCreateConnection(string $uri)
    {
        $redis = RedisAdapter::createConnection('redis://'.$uri);
        self::assertInstanceOf(\Redis::class, $redis);
        self::assertTrue($redis->isConnected());
        self::assertSame(0, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://'.$uri.'/2');
        self::assertSame(2, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://'.$uri, ['timeout' => 3]);
        self::assertEquals(3, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://'.$uri.'?timeout=4');
        self::assertEquals(4, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://'.$uri, ['read_timeout' => 5]);
        self::assertEquals(5, $redis->getReadTimeout());
    }

    public function testCreateTlsConnection()
    {
        $redis = RedisAdapter::createConnection('rediss:?host[h1]&host[h2]&host[/foo:]');
        self::assertInstanceOf(\RedisArray::class, $redis);
        self::assertSame(['tls://h1:6379', 'tls://h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $redisHost = getenv('REDIS_HOST');

        $redis = RedisAdapter::createConnection('rediss://'.$redisHost.'?lazy=1');
        self::assertInstanceOf(RedisProxy::class, $redis);
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection(string $dsn)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Redis connection ');
        RedisAdapter::createConnection($dsn);
    }

    public function provideFailedCreateConnection(): array
    {
        return [
            ['redis://localhost:1234'],
            ['redis://foo@localhost'],
            ['redis://localhost/123'],
            ['redis:///some/local/path'],
        ];
    }

    /**
     * @dataProvider provideInvalidCreateConnection
     */
    public function testInvalidCreateConnection(string $dsn)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid Redis DSN');
        RedisAdapter::createConnection($dsn);
    }

    public function provideInvalidCreateConnection(): array
    {
        return [
            ['redis://localhost/foo'],
            ['foo://localhost'],
            ['redis://'],
        ];
    }
}
