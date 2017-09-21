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
use Symfony\Component\Dsn\Factory\MemcachedConnectionFactory;

/**
 * @requires extension memcached
 */
class MemcachedConnectionFactoryTest extends TestCase
{
    public function testOptions()
    {
        $client = MemcachedConnectionFactory::createConnection(array(), array(
            'libketama_compatible' => false,
            'distribution' => 'modula',
            'compression' => true,
            'serializer' => 'php',
            'hash' => 'md5',
        ));

        $this->assertSame(\Memcached::SERIALIZER_PHP, $client->getOption(\Memcached::OPT_SERIALIZER));
        $this->assertSame(\Memcached::HASH_MD5, $client->getOption(\Memcached::OPT_HASH));
        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        if (version_compare(phpversion('memcached'), '2.2.0', '>=')) {
            $this->assertSame(0, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
        }
        $this->assertSame(\Memcached::DISTRIBUTION_MODULA, $client->getOption(\Memcached::OPT_DISTRIBUTION));
    }

    /**
     * @dataProvider provideBadOptions
     * @expectedException \ErrorException
     * @expectedExceptionMessage constant(): Couldn't find constant Memcached::
     */
    public function testBadOptions($name, $value)
    {
        MemcachedConnectionFactory::createConnection(array(), array($name => $value));
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
        $client = MemcachedConnectionFactory::createConnection(array());

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port)
    {
        $client1 = MemcachedConnectionFactory::createConnection($dsn);
        $client2 = MemcachedConnectionFactory::createConnection(array($dsn));
        $expect = array(
            'host' => $host,
            'port' => $port,
        );

        $f = function ($s) { return array('host' => $s['host'], 'port' => $s['port']); };
        $this->assertSame(array($expect), array_map($f, $client1->getServerList()));
        $this->assertSame(array($expect), array_map($f, $client2->getServerList()));
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
        if (ini_get('memcached.use_sasl')) {
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
        if (ini_get('memcached.use_sasl')) {
            yield array(
                'memcached://user:password@/var/local/run/memcached.socket?weight=25',
                '/var/local/run/memcached.socket',
                0,
            );
        }
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testDsnWithOptions($dsn, array $options, array $expectedOptions)
    {
        $client = MemcachedConnectionFactory::createConnection($dsn, $options);

        foreach ($expectedOptions as $option => $expect) {
            $this->assertSame($expect, $client->getOption($option));
        }
    }

    public function provideDsnWithOptions()
    {
        if (!class_exists('\Memcached')) {
            self::markTestSkipped('Extension memcached required.');
        }

        yield array(
            'memcached://localhost:11222?retry_timeout=10',
            array(\Memcached::OPT_RETRY_TIMEOUT => 8),
            array(\Memcached::OPT_RETRY_TIMEOUT => 10),
        );
        yield array(
            'memcached://localhost:11222?socket_recv_size=1&socket_send_size=2',
            array(\Memcached::OPT_RETRY_TIMEOUT => 8),
            array(\Memcached::OPT_SOCKET_RECV_SIZE => 1, \Memcached::OPT_SOCKET_SEND_SIZE => 2, \Memcached::OPT_RETRY_TIMEOUT => 8),
        );
    }
}
