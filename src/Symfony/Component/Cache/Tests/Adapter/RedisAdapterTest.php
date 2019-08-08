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

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Traits\RedisProxy;

class RedisAdapterTest extends AbstractRedisAdapterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$redis = AbstractAdapter::createConnection('redis://'.getenv('REDIS_HOST'), ['lazy' => true]);
    }

    public function createCachePool($defaultLifetime = 0)
    {
        $adapter = parent::createCachePool($defaultLifetime);
        $this->assertInstanceOf(RedisProxy::class, self::$redis);

        return $adapter;
    }

    /**
     * @dataProvider provideValidSchemes
     */
    public function testCreateConnection($dsnScheme)
    {
        $redis = RedisAdapter::createConnection($dsnScheme.':?host[h1]&host[h2]&host[/foo:]');
        $this->assertInstanceOf(\RedisArray::class, $redis);
        $this->assertSame(['h1:6379', 'h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $redisHost = getenv('REDIS_HOST');

        $redis = RedisAdapter::createConnection($dsnScheme.'://'.$redisHost);
        $this->assertInstanceOf(\Redis::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisAdapter::createConnection($dsnScheme.'://'.$redisHost.'/2');
        $this->assertSame(2, $redis->getDbNum());

        $redis = RedisAdapter::createConnection($dsnScheme.'://'.$redisHost, ['timeout' => 3]);
        $this->assertEquals(3, $redis->getTimeout());

        $redis = RedisAdapter::createConnection($dsnScheme.'://'.$redisHost.'?timeout=4');
        $this->assertEquals(4, $redis->getTimeout());

        $redis = RedisAdapter::createConnection($dsnScheme.'://'.$redisHost, ['read_timeout' => 5]);
        $this->assertEquals(5, $redis->getReadTimeout());
    }

    /**
     * @dataProvider provideFailedCreateConnection
     */
    public function testFailedCreateConnection($dsn)
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Redis connection failed');
        RedisAdapter::createConnection($dsn);
    }

    public function provideFailedCreateConnection()
    {
        return [
            ['redis://localhost:1234'],
            ['redis://foo@localhost'],
            ['redis://localhost/123'],
        ];
    }

    /**
     * @dataProvider provideInvalidCreateConnection
     */
    public function testInvalidCreateConnection($dsn)
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid Redis DSN');
        RedisAdapter::createConnection($dsn);
    }

    public function provideValidSchemes()
    {
        return [
            ['redis'],
            ['rediss'],
        ];
    }

    public function provideInvalidCreateConnection()
    {
        return [
            ['foo://localhost'],
            ['redis://'],
        ];
    }
}
