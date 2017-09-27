<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Store\MemcachedStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension memcached
 */
class MemcachedStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        if (false === $memcached->getStats()) {
            self::markTestSkipped('Unable to connect to the memcache host');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 1000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);

        return new MemcachedStore($memcached);
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Memcached expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    public function testDefaultOptions()
    {
        $this->assertTrue(MemcachedStore::isSupported());

        $client = MemcachedStore::createConnection('memcached://127.0.0.1');

        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_BINARY_PROTOCOL));
        $this->assertSame(1, $client->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE));
    }

    /**
     * @dataProvider provideServersSetting
     */
    public function testServersSetting($dsn, $host, $port)
    {
        $client1 = MemcachedStore::createConnection($dsn);
        $client3 = MemcachedStore::createConnection(array($host, $port));
        $expect = array(
            'host' => $host,
            'port' => $port,
        );

        $f = function ($s) { return array('host' => $s['host'], 'port' => $s['port']); };
        $this->assertSame(array($expect), array_map($f, $client1->getServerList()));
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
        $client = MemcachedStore::createConnection($dsn, $options);

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
