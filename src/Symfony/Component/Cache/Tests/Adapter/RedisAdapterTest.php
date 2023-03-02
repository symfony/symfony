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
class RedisAdapterTest extends AbstractRedisAdapterTestCase
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
        $this->assertInstanceOf(RedisProxy::class, self::$redis);

        return $adapter;
    }

    public function testCreateHostConnection()
    {
        $redis = RedisAdapter::createConnection('redis:?host[h1]&host[h2]&host[/foo:]');
        $this->assertInstanceOf(\RedisArray::class, $redis);
        $this->assertSame(['h1:6379', 'h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $this->doTestCreateConnection(getenv('REDIS_HOST'));
    }

    public function testCreateSocketConnection()
    {
        if (!getenv('REDIS_SOCKET') || !file_exists(getenv('REDIS_SOCKET'))) {
            $this->markTestSkipped('Redis socket not found');
        }

        $this->doTestCreateConnection(getenv('REDIS_SOCKET'));
    }

    private function doTestCreateConnection(string $uri)
    {
        $redis = RedisAdapter::createConnection('redis://'.$uri);
        $this->assertInstanceOf(\Redis::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://'.$uri.'/');
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://'.$uri.'/2');
        $this->assertSame(2, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://'.$uri, ['timeout' => 3]);
        $this->assertEquals(3, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://'.$uri.'?timeout=4');
        $this->assertEquals(4, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://'.$uri, ['read_timeout' => 5]);
        $this->assertEquals(5, $redis->getReadTimeout());
    }

    public function testCreateTlsConnection()
    {
        $redis = RedisAdapter::createConnection('rediss:?host[h1]&host[h2]&host[/foo:]');
        $this->assertInstanceOf(\RedisArray::class, $redis);
        $this->assertSame(['tls://h1:6379', 'tls://h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $redisHost = getenv('REDIS_HOST');

        $redis = RedisAdapter::createConnection('rediss://'.$redisHost.'?lazy=1');
        $this->assertInstanceOf(RedisProxy::class, $redis);
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis connection ');
        RedisAdapter::createConnection($dsn);
    }

    public static function provideFailedCreateConnection(): array
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Redis DSN');
        RedisAdapter::createConnection($dsn);
    }

    public static function provideInvalidCreateConnection(): array
    {
        return [
            ['redis://localhost/foo'],
            ['foo://localhost'],
            ['redis://'],
        ];
    }

    public function testAclUserPasswordAuth()
    {
        $redis = RedisAdapter::createConnection('redis://'.getenv('REDIS_HOST'));

        if (version_compare($redis->info()['redis_version'], '6.0', '<')) {
            $this->markTestSkipped('Redis server >= 6.0 required');
        }

        $this->assertTrue($redis->acl('SETUSER', 'alice', 'on'));
        $this->assertTrue($redis->acl('SETUSER', 'alice', '>password'));
        $this->assertTrue($redis->acl('SETUSER', 'alice', 'allkeys'));
        $this->assertTrue($redis->acl('SETUSER', 'alice', '+@all'));

        $redis = RedisAdapter::createConnection('redis://alice:password@'.getenv('REDIS_HOST'));
        $this->assertTrue($redis->set(__FUNCTION__, 'value2'));

        $redis = RedisAdapter::createConnection('redis://'.getenv('REDIS_HOST'));
        $this->assertSame(1, $redis->acl('DELUSER', 'alice'));
    }
}
