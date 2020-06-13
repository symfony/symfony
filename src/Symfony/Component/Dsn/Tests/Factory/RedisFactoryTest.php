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
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Dsn\Exception\FailedToConnectException;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;
use Symfony\Component\Dsn\Exception\InvalidDsnException;
use Symfony\Component\Dsn\Factory\RedisFactory;

/**
 * @requires extension redis
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RedisFactoryTest extends TestCase
{
    /**
     * @dataProvider provideValidSchemes
     */
    public function testCreate(string $dsnScheme)
    {
        $redis = RedisFactory::create($dsnScheme.':?host[h1]&host[h2]&host[/foo:]');
        $this->assertInstanceOf(\RedisArray::class, $redis);
        $this->assertSame(['h1:6379', 'h2:6379', '/foo'], $redis->_hosts());
        @$redis = null; // some versions of phpredis connect on destruct, let's silence the warning

        $redisHost = getenv('REDIS_HOST');

        $redis = RedisFactory::create($dsnScheme.'://'.$redisHost);
        $this->assertInstanceOf(\Redis::class, $redis);
        $this->assertTrue($redis->isConnected());
        $this->assertSame(0, $redis->getDbNum());

        $redis = RedisFactory::create($dsnScheme.'://'.$redisHost.'/2');
        $this->assertSame(2, $redis->getDbNum());

        $redis = RedisFactory::create($dsnScheme.'://'.$redisHost.'?timeout=4');
        $this->assertEquals(4, $redis->getTimeout());

        $redis = RedisFactory::create($dsnScheme.'://'.$redisHost.'?read_timeout=5');
        $this->assertEquals(5, $redis->getReadTimeout());
    }

    /**
     * @dataProvider provideFailedCreate
     */
    public function testFailedCreate($dsn)
    {
        $this->expectException(FailedToConnectException::class);
        $this->expectExceptionMessage('Redis connection "'.$dsn.'" failed');
        RedisFactory::create($dsn);
    }

    public function provideFailedCreate()
    {
        yield ['redis://localhost:1234'];
        yield ['redis://foo@localhost'];
        yield ['redis://localhost/123'];
    }

    /**
     * @dataProvider provideInvalidCreate
     */
    public function testInvalidCreate($dsn)
    {
        $this->expectException(InvalidDsnException::class);
        $this->expectExceptionMessage('Invalid Redis DSN');
        RedisFactory::create($dsn);
    }

    public function provideInvalidCreate()
    {
        yield ['foo://localhost'];
        yield ['redis://'];
    }


    public function provideValidSchemes(): array
    {
        return [
            ['redis'],
            ['rediss'],
        ];
    }

}
