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

use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Exception\CacheException;

/**
 * @group integration
 */
class MemcachedAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
        'testClearPrefix' => 'Memcached cannot clear by prefix',
    ];

    protected static $client;

    public static function setUpBeforeClass(): void
    {
        if (!MemcachedAdapter::isSupported()) {
            throw new SkippedTestSuiteError('Extension memcached > 3.1.5 required.');
        }
        self::$client = AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST'), ['binary_protocol' => false]);
        self::$client->get('foo');
        $code = self::$client->getResultCode();

        if (\Memcached::RES_SUCCESS !== $code && \Memcached::RES_NOTFOUND !== $code) {
            throw new SkippedTestSuiteError('Memcached error: '.strtolower(self::$client->getResultMessage()));
        }
    }

    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null, string $namespace = null): CacheItemPoolInterface
    {
        $client = $defaultLifetime ? AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST')) : self::$client;

        return new MemcachedAdapter($client, $namespace ?? str_replace('\\', '.', __CLASS__), $defaultLifetime);
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
     */
    public function testBadOptions($name, $value)
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Undefined constant Memcached::');

        MemcachedAdapter::createConnection([], [$name => $value]);
    }

    public static function provideBadOptions(): array
    {
        return [
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

    public function testOptionSerializer()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('MemcachedAdapter: "serializer" option must be "php" or "igbinary".');
        if (!\Memcached::HAVE_JSON) {
            $this->markTestSkipped('Memcached::HAVE_JSON required');
        }

        new MemcachedAdapter(MemcachedAdapter::createConnection([], ['serializer' => 'json']));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting(string $dsn, string $host, int $port)
    {
        $client1 = MemcachedAdapter::createConnection($dsn);
        $client2 = MemcachedAdapter::createConnection([$dsn]);
        $client3 = MemcachedAdapter::createConnection([[$host, $port]]);
        $expect = [
            'host' => $host,
            'port' => $port,
        ];

        $f = fn ($s) => ['host' => $s['host'], 'port' => $s['port']];
        $this->assertSame([$expect], array_map($f, $client1->getServerList()));
        $this->assertSame([$expect], array_map($f, $client2->getServerList()));
        $this->assertSame([$expect], array_map($f, $client3->getServerList()));
    }

    public static function provideServersSetting(): iterable
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
        if (filter_var(\ini_get('memcached.use_sasl'), \FILTER_VALIDATE_BOOL)) {
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
        if (filter_var(\ini_get('memcached.use_sasl'), \FILTER_VALIDATE_BOOL)) {
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
    public function testDsnWithOptions(string $dsn, array $options, array $expectedOptions)
    {
        $client = MemcachedAdapter::createConnection($dsn, $options);

        foreach ($expectedOptions as $option => $expect) {
            $this->assertSame($expect, $client->getOption($option));
        }
    }

    public static function provideDsnWithOptions(): iterable
    {
        if (!class_exists(\Memcached::class)) {
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

    public function testMultiServerDsn()
    {
        $dsn = 'memcached:?host[localhost]&host[localhost:12345]&host[/some/memcached.sock:]=3';
        $client = MemcachedAdapter::createConnection($dsn);

        $expected = [
            0 => [
                'host' => 'localhost',
                'port' => 11211,
                'type' => 'TCP',
            ],
            1 => [
                'host' => 'localhost',
                'port' => 12345,
                'type' => 'TCP',
            ],
            2 => [
                'host' => '/some/memcached.sock',
                'port' => 0,
                'type' => 'SOCKET',
            ],
        ];
        $this->assertSame($expected, $client->getServerList());

        $dsn = 'memcached://localhost?host[foo.bar]=3';
        $client = MemcachedAdapter::createConnection($dsn);

        $expected = [
            0 => [
                'host' => 'localhost',
                'port' => 11211,
                'type' => 'TCP',
            ],
            1 => [
                'host' => 'foo.bar',
                'port' => 11211,
                'type' => 'TCP',
            ],
        ];
        $this->assertSame($expected, $client->getServerList());
    }

    public function testKeyEncoding()
    {
        $reservedMemcachedCharacters = " \n\r\t\v\f\0";

        $namespace = $reservedMemcachedCharacters.random_int(0, \PHP_INT_MAX);
        $pool = $this->createCachePool(0, null, $namespace);

        /**
         * Choose a key that is below {@see \Symfony\Component\Cache\Adapter\MemcachedAdapter::$maxIdLength} so that
         * {@see \Symfony\Component\Cache\Traits\AbstractTrait::getId()} does not shorten the key but choose special
         * characters that would be encoded and therefore increase the key length over the Memcached limit.
         */
        // 250 is Memcached’s max key length, 7 bytes for prefix seed
        $key = str_repeat('%', 250 - 7 - \strlen($reservedMemcachedCharacters) - \strlen($namespace)).$reservedMemcachedCharacters;

        self::assertFalse($pool->hasItem($key));

        $item = $pool->getItem($key);
        self::assertFalse($item->isHit());
        self::assertSame($key, $item->getKey());

        self::assertTrue($pool->save($item->set('foobar')));

        self::assertTrue($pool->hasItem($key));
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit());
        self::assertSame($key, $item->getKey());

        self::assertTrue($pool->deleteItem($key));
        self::assertFalse($pool->hasItem($key));
    }
}
