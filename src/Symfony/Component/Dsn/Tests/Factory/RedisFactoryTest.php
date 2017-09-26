<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dsn\Factory\RedisFactory;

/**
 * @requires extension redis
 */
class RedisFactoryTest extends TestCase
{
    public function testCreate()
    {
        $redisHost = getenv('REDIS_HOST');

        $redis = RedisFactory::create('redis://'.$redisHost);
        $this->assertInstanceOf(\Redis::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisFactory::create('redis://'.$redisHost.'/2');
        $this->assertSame(2, $redis->getDbNum());

        $redis = RedisFactory::create('redis://'.$redisHost, array('timeout' => 3));
        $this->assertEquals(3, $redis->getTimeout());

        $redis = RedisFactory::create('redis://'.$redisHost.'?timeout=4');
        $this->assertEquals(4, $redis->getTimeout());

        $redis = RedisFactory::create('redis://'.$redisHost, array('read_timeout' => 5));
        $this->assertEquals(5, $redis->getReadTimeout());
    }

    /**
     * @dataProvider provideFailedCreate
     * @expectedException \Symfony\Component\Dsn\Exception\InvalidArgumentException
     * @expectedExceptionMessage Redis connection failed
     */
    public function testFailedCreate($dsn)
    {
        RedisFactory::create($dsn);
    }

    public function provideFailedCreate()
    {
        yield array('redis://localhost:1234');
        yield array('redis://foo@localhost');
        yield array('redis://localhost/123');
    }

    /**
     * @dataProvider provideInvalidCreate
     * @expectedException \Symfony\Component\Dsn\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid Redis DSN
     */
    public function testInvalidCreate($dsn)
    {
        RedisFactory::create($dsn);
    }

    public function provideInvalidCreate()
    {
        yield array('foo://localhost');
        yield array('redis://');
    }
}
