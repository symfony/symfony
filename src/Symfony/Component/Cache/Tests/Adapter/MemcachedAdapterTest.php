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
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class MemcachedAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    ];

    protected static $client;

    public static function setupBeforeClass()
    {
        if (!MemcachedAdapter::isSupported()) {
            self::markTestSkipped('Extension memcached >=2.2.0 required.');
        }
        self::$client = AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST'), ['binary_protocol' => false]);
        self::$client->get('foo');
        $code = self::$client->getResultCode();

        if (\Memcached::RES_SUCCESS !== $code && \Memcached::RES_NOTFOUND !== $code) {
            self::markTestSkipped('Memcached error: '.strtolower(self::$client->getResultMessage()));
        }
    }

    public function createCachePool($defaultLifetime = 0)
    {
        $client = $defaultLifetime ? AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST')) : self::$client;

        return new MemcachedAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testOptions()
    {
        $client = MemcachedAdapter::createConnection([], [
            'libketama_compatible' => false,
            'distribution' => 'modula',
            'compression' => true,
            'serializer' => 'php',
            'hash' => 'md5',
        ]);

        $this->assertSame(\Memcached::SERIALIZER_PHP, $client->getOption(\Memcached::OPT_SERIALIZER));
        $this->assertSame(\Memcached::HASH_MD5, $client->getOption(\Memcached::OPT_HASH));
        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(0, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
        $this->assertSame(\Memcached::DISTRIBUTION_MODULA, $client->getOption(\Memcached::OPT_DISTRIBUTION));
    }

    /**
     * @dataProvider provideBadOptions
     * @expectedException \ErrorException
     * @expectedExceptionMessage constant(): Couldn't find constant Memcached::
     */
    public function testBadOptions($name, $value)
    {
        MemcachedAdapter::createConnection([], [$name => $value]);
    }

    public function provideBadOptions()
    {
        return [
            ['foo', 'bar'],
            ['hash', 'zyx'],
            ['serializer', 'zyx'],
            ['distribution', 'zyx'],
        ];
    }

    public function testDefaultOptions()
    {
        $this->assertTrue(MemcachedAdapter::isSupported());

        $client = MemcachedAdapter::createConnection([]);

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_TCP_NODELAY));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\CacheException
     * @expectedExceptionMessage MemcachedAdapter: "serializer" option must be "php" or "igbinary".
     */
    public function testOptionSerializer()
    {
        if (!\Memcached::HAVE_JSON) {
            $this->markTestSkipped('Memcached::HAVE_JSON required');
        }

        new MemcachedAdapter(MemcachedAdapter::createConnection([], ['serializer' => 'json']));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port)
    {
        $client1 = MemcachedAdapter::createConnection($dsn);
        $client2 = MemcachedAdapter::createConnection([$dsn]);
        $client3 = MemcachedAdapter::createConnection([[$host, $port]]);
        $expect = [
            'host' => $host,
            'port' => $port,
        ];

        $f = function ($s) { return ['host' => $s['host'], 'port' => $s['port']]; };
        $this->assertSame([$expect], array_map($f, $client1->getServerList()));
        $this->assertSame([$expect], array_map($f, $client2->getServerList()));
        $this->assertSame([$expect], array_map($f, $client3->getServerList()));
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
        if (filter_var(ini_get('memcached.use_sasl'), FILTER_VALIDATE_BOOLEAN)) {
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
        if (filter_var(ini_get('memcached.use_sasl'), FILTER_VALIDATE_BOOLEAN)) {
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
    public function testDsnWithOptions($dsn, array $options, array $expectedOptions)
    {
        $client = MemcachedAdapter::createConnection($dsn, $options);

        foreach ($expectedOptions as $option => $expect) {
            $this->assertSame($expect, $client->getOption($option));
        }
    }

    public function provideDsnWithOptions()
    {
        if (!class_exists('\Memcached')) {
            self::markTestSkipped('Extension memcached required.');
        }

        yield [
            'memcached://localhost:11222?retry_timeout=10',
            [\Memcached::OPT_RETRY_TIMEOUT => 8],
            [\Memcached::OPT_RETRY_TIMEOUT => 10],
        ];
        yield [
            'memcached://localhost:11222?socket_recv_size=1&socket_send_size=2',
            [\Memcached::OPT_RETRY_TIMEOUT => 8],
            [\Memcached::OPT_SOCKET_RECV_SIZE => 1, \Memcached::OPT_SOCKET_SEND_SIZE => 2, \Memcached::OPT_RETRY_TIMEOUT => 8],
        ];
    }

    public function testClear()
    {
        $this->assertTrue($this->createCachePool()->clear());
    }
}
