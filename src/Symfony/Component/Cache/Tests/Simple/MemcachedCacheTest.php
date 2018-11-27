<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Simple\MemcachedCache;

class MemcachedCacheTest extends CacheTestCase
{
    protected $skippedTests = array(
        'testSetTtl' => 'Testing expiration slows down the test suite',
        'testSetMultipleTtl' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    protected static $client;

    public static function setupBeforeClass()
    {
        if (!MemcachedCache::isSupported()) {
            self::markTestSkipped('Extension memcached >=2.2.0 required.');
        }
        self::$client = AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST'));
        self::$client->get('foo');
        $code = self::$client->getResultCode();

        if (\Memcached::RES_SUCCESS !== $code && \Memcached::RES_NOTFOUND !== $code) {
            self::markTestSkipped('Memcached error: '.strtolower(self::$client->getResultMessage()));
        }
    }

    public function createSimpleCache($defaultLifetime = 0)
    {
        $client = $defaultLifetime ? AbstractAdapter::createConnection('memcached://'.getenv('MEMCACHED_HOST'), array('binary_protocol' => false)) : self::$client;

        return new MemcachedCache($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testCreatePersistentConnectionShouldNotDupServerList()
    {
        $instance = MemcachedCache::createConnection('memcached://'.getenv('MEMCACHED_HOST'), array('persistent_id' => 'persistent'));
        $this->assertCount(1, $instance->getServerList());

        $instance = MemcachedCache::createConnection('memcached://'.getenv('MEMCACHED_HOST'), array('persistent_id' => 'persistent'));
        $this->assertCount(1, $instance->getServerList());
    }

    public function testOptions()
    {
        $client = MemcachedCache::createConnection(array(), array(
            'libketama_compatible' => false,
            'distribution' => 'modula',
            'compression' => true,
            'serializer' => 'php',
            'hash' => 'md5',
        ));

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
        MemcachedCache::createConnection(array(), array($name => $value));
    }

    public function provideBadOptions()
    {
        return array(
            array('foo', 'bar'),
            array('hash', 'zyx'),
            array('serializer', 'zyx'),
            array('distribution', 'zyx'),
        );
    }

    public function testDefaultOptions()
    {
        $this->assertTrue(MemcachedCache::isSupported());

        $client = MemcachedCache::createConnection(array());

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
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

        new MemcachedCache(MemcachedCache::createConnection(array(), array('serializer' => 'json')));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port)
    {
        $client1 = MemcachedCache::createConnection($dsn);
        $client2 = MemcachedCache::createConnection(array($dsn));
        $client3 = MemcachedCache::createConnection(array(array($host, $port)));
        $expect = array(
            'host' => $host,
            'port' => $port,
        );

        $f = function ($s) { return array('host' => $s['host'], 'port' => $s['port']); };
        $this->assertSame(array($expect), array_map($f, $client1->getServerList()));
        $this->assertSame(array($expect), array_map($f, $client2->getServerList()));
        $this->assertSame(array($expect), array_map($f, $client3->getServerList()));
    }

    public function provideServersSetting()
    {
        yield array(
            'memcached://127.0.0.1/50',
            '127.0.0.1',
            11211,
        );
        yield array(
            'memcached://localhost:11222?weight=25',
            'localhost',
            11222,
        );
        if (filter_var(ini_get('memcached.use_sasl'), FILTER_VALIDATE_BOOLEAN)) {
            yield array(
                'memcached://user:password@127.0.0.1?weight=50',
                '127.0.0.1',
                11211,
            );
        }
        yield array(
            'memcached:///var/run/memcached.sock?weight=25',
            '/var/run/memcached.sock',
            0,
        );
        yield array(
            'memcached:///var/local/run/memcached.socket?weight=25',
            '/var/local/run/memcached.socket',
            0,
        );
        if (filter_var(ini_get('memcached.use_sasl'), FILTER_VALIDATE_BOOLEAN)) {
            yield array(
                'memcached://user:password@/var/local/run/memcached.socket?weight=25',
                '/var/local/run/memcached.socket',
                0,
            );
        }
    }
}
