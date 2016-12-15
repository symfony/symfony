<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter\Client;

use Symfony\Component\Cache\Adapter\Client\MemcachedClient;

class MemcachedClientTest extends \PHPUnit_Framework_TestCase
{
    public static function setupBeforeClass()
    {
        if (!MemcachedClient::isSupported()) {
            self::markTestSkipped('Memcached extension >= 2.2.0 required for test.');
        }

        parent::setupBeforeClass();
    }

    public function testIsSupported()
    {
        $this->assertTrue(MemcachedClient::isSupported());
    }

    public function testServersNoDuplicates()
    {
        $dsns = array(
            'memcached://127.0.0.1:11211',
            'memcached://127.0.0.1:11211',
            'memcached://127.0.0.1:11211',
            'memcached://127.0.0.1:11211',
        );

        $this->assertCount(1, MemcachedClient::create($dsns)->getServerList());
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port, $type)
    {
        $client1 = MemcachedClient::create($dsn);
        $client2 = MemcachedClient::create(array($dsn));
        $expect = array(
            'host' => $host,
            'port' => $port,
            'type' => $type,
        );

        $this->assertSame(array($expect), $client1->getServerList());
        $this->assertSame(array($expect), $client2->getServerList());
    }

    public function provideServersSetting()
    {
        return array(
            array(
                'memcached:',
                'localhost',
                11211,
                'TCP',
            ),
            array(
                'memcached://127.0.0.1?weight=50',
                '127.0.0.1',
                11211,
                'TCP',
            ),
            array(
                 'memcached://localhost:11222?weight=25',
                'localhost',
                11222,
                'TCP',
            ),
            array(
                'memcached://user:password@127.0.0.1?weight=50',
                '127.0.0.1',
                11211,
                'TCP',
            ),
            array(
                'memcached:///var/run/memcached.sock?weight=25',
                '/var/run/memcached.sock',
                11211,
                'SOCKET',
            ),
            array(
                'memcached:///var/local/run/memcached.socket/?weight=25',
                '/var/local/run/memcached.socket',
                11211,
                'SOCKET',
            ),
            array(
                'memcached://user:password@/var/local/run/memcached.socket/?weight=25',
                '/var/local/run/memcached.socket',
                11211,
                'SOCKET',
            ),
        );
    }

    /**
     * @dataProvider provideServersInvalid
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid server ([0-9]+ )?DSN:}
     */
    public function testServersInvalid($server)
    {
        MemcachedClient::create(array($server));
    }

    public function provideServersInvalid()
    {
        return array(
            array('redis://127.0.0.1'),
            array('memcached://localhost:bad-port'),
        );
    }

    /**
     * @dataProvider provideOptionsSetting
     */
    public function testOptionsSetting($named, $value, $resolvedNamed, $resolvedValue)
    {
        $client = MemcachedClient::create(array(), array($named => $value));

        $this->assertSame($resolvedValue, $client->getOption($resolvedNamed));
    }

    public function provideOptionsSetting()
    {
        return array(
            array('serializer', 'igbinary', \Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_IGBINARY),
            array('hash', 'md5', \Memcached::OPT_HASH, \Memcached::HASH_MD5),
            array('compression', true, \Memcached::OPT_COMPRESSION, true),
            array('libketama_compatible', false, \Memcached::OPT_LIBKETAMA_COMPATIBLE, 0),
            array('distribution', 'consistent', \Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT),
        );
    }

    /**
     * @dataProvider provideOptionsInvalid
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid option( named)?: ([a-z']+)(=[a-z']+)?}
     */
    public function testOptionsInvalid($named, $value)
    {
        MemcachedClient::create(array(), array($named => $value));
    }

    public function provideOptionsInvalid()
    {
        return array(
            array('invalid_named', 'hash_md5'),
            array('prefix_key', str_repeat('abcdef', 128)),
        );
    }

    public function testOptionsDefault()
    {
        $client = MemcachedClient::create();

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegex Could not set SASL authentication: \(Memcached::setSaslAuthData\(\): .*binary protocol.*
     */
    public function testSaslError()
    {
        MemcachedClient::create(array('memcached://user:pass@127.0.0.1'), array('binary_protocol' => false));
    }
}
