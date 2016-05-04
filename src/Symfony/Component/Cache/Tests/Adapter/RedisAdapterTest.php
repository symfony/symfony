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

class RedisAdapterTest extends AbstractRedisAdapterTest
{
    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1');
    }

    public function testCreateConnection()
    {
        $redis = RedisAdapter::createConnection('redis://localhost');
        $this->assertInstanceOf(\Redis::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://localhost/2');
        $this->assertSame(2, $redis->getDbNum());

        $redis = RedisAdapter::createConnection('redis://localhost', array('timeout' => 3));
        $this->assertEquals(3, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://localhost?timeout=4');
        $this->assertEquals(4, $redis->getTimeout());

        $redis = RedisAdapter::createConnection('redis://localhost', array('read_timeout' => 5));
        $this->assertEquals(5, $redis->getReadTimeout());
    }

    /**
     * @dataProvider provideFailedCreateConnection
     * @expectedException Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Redis connection failed
     */
    public function testFailedCreateConnection($dsn)
    {
        RedisAdapter::createConnection($dsn);
    }

    public function provideFailedCreateConnection()
    {
        return array(
            array('redis://localhost:1234'),
            array('redis://foo@localhost'),
            array('redis://localhost/123'),
        );
    }

    /**
     * @dataProvider provideInvalidCreateConnection
     * @expectedException Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid Redis DSN
     */
    public function testInvalidCreateConnection($dsn)
    {
        RedisAdapter::createConnection($dsn);
    }

    public function provideInvalidCreateConnection()
    {
        return array(
            array('foo://localhost'),
            array('redis://'),
        );
    }
}
