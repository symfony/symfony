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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dsn\Factory\MemcachedFactory;

/**
 * @requires extension memcached
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MemcachedFactoryTest extends TestCase
{
    /**
     * @dataProvider provideBadOptions
     */
    public function testBadOptions($name, $value)
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('constant(): Couldn\'t find constant Memcached::');
        MemcachedFactory::create(sprintf('memcached://localhost?%s=%s', $name, $value));
    }

    public function provideBadOptions()
    {
        yield ['foo', 'bar'];
        yield ['hash', 'zyx'];
        yield ['serializer', 'zyx'];
        yield ['distribution', 'zyx'];
    }

    public function testDefaultOptions()
    {
        $client = MemcachedFactory::create('memcached://localhost');

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port)
    {
        $client = MemcachedFactory::create($dsn);
        $expect = [
            'host' => $host,
            'port' => $port,
        ];

        $f = function ($s) { return ['host' => $s['host'], 'port' => $s['port']]; };
        $this->assertSame([$expect], array_map($f, $client->getServerList()));
    }

    public function provideServersSetting()
    {
        yield [
            'memcached://127.0.0.1/50',
            '127.0.0.1',
            11211,
        ];
        yield [
            'memcached://localhost:11222?weight=25',
            'localhost',
            11222,
        ];
        if (ini_get('memcached.use_sasl')) {
            yield [
                'memcached://user:password@127.0.0.1?weight=50',
                '127.0.0.1',
                11211,
            ];
        }
        yield [
            'memcached:///var/run/memcached.sock?weight=25',
            '/var/run/memcached.sock',
            0,
        ];
        yield [
            'memcached:///var/local/run/memcached.socket?weight=25',
            '/var/local/run/memcached.socket',
            0,
        ];
        if (ini_get('memcached.use_sasl')) {
            yield [
                'memcached://user:password@/var/local/run/memcached.socket?weight=25',
                '/var/local/run/memcached.socket',
                0,
            ];
        }
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testDsnWithOptions($dsn, array $expectedOptions)
    {
        $client = MemcachedFactory::create($dsn);

        foreach ($expectedOptions as $option => $expect) {
            $this->assertSame($expect, $client->getOption($option));
        }
    }

    public function provideDsnWithOptions()
    {
        yield [
            'memcached://localhost:11222?retry_timeout=10',
            [\Memcached::OPT_RETRY_TIMEOUT => 10],
        ];
        yield [
            'memcached(memcached://localhost:11222?socket_recv_size=1&socket_send_size=2)?retry_timeout=8',
            [\Memcached::OPT_SOCKET_RECV_SIZE => 1, \Memcached::OPT_SOCKET_SEND_SIZE => 2, \Memcached::OPT_RETRY_TIMEOUT => 8],
        ];
    }
}
