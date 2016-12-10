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

use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class MemcachedAdapterTest extends AbstractMemcacheAdapterTest
{
    protected static $extension = 'memcached';

    private static function defaultConnectionOptions()
    {
        return array(
            'OPT_DISTRIBUTION' => 'DISTRIBUTION_CONSISTENT',
            'OPT_LIBKETAMA_COMPATIBLE' => true,
            'OPT_COMPRESSION' => true,
        );
    }

    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();

        if (!version_compare(phpversion('memcached'), '2.1.0', '>')) {
            self::markTestSkipped('Extension memcached >2.1.0 required.');
        }

        self::$client = MemcachedAdapter::create(static::defaultConnectionServer(), static::defaultConnectionOptions())->getClient();
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new MemcachedAdapter(self::$client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    /**
     * @group memcachedAdapter
     */
    public function testCreateAdaptor()
    {
        $adapter = MemcachedAdapter::create();
        $memcache = $adapter->getClient();

        $this->assertInstanceOf(MemcachedAdapter::class, $adapter,
            'Adapter created should be instance of MemcachedAdapter.');

        $this->assertInstanceOf(\Memcached::class, $memcache,
            'Client created should be instance of Memcached.');

        $this->assertCount(0, $memcache->getServerList(),
            'No registered servers should exist with Memcached client.');

        $this->assertTrue($adapter->setup(array(static::defaultConnectionServer())),
            'Expects true return when no server/option errors.');

        $this->assertCount(1, $memcache->getServerList(),
            'A single registered servers should exist with Memcached client.');

        $this->assertTrue($adapter->setup(array(static::defaultConnectionServer()), static::defaultConnectionOptions()),
            'Expects true return when no server/option errors.');

        $this->assertTrue($memcache->getOption(\Memcached::OPT_COMPRESSION),
            'Ensure out configured option has been applied to Memcached client.');

        $this->assertSame(1, $memcache->getOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE),
            'Ensure out configured option has been applied to Memcached client.');
    }

    /**
     * @group memcachedAdapter
     */
    public function testDuplicateServersAreNotRegistered()
    {
        $adapter = MemcachedAdapter::create();
        $memcache = $adapter->getClient();

        $this->assertCount(0, $memcache->getServerList(),
            'No registered servers should exist with \Memcached client.');

        for ($i = 0; $i < 10; ++$i) {
            $this->assertTrue($adapter->setup(array(static::defaultConnectionServer())),
                'Expects true return when no server/option errors.');

            $this->assertCount(1, $memcache->getServerList(),
                'A single registered server should exist with \Memcached client when same server added multiple times.');
        }
    }

    /**
     * @group memcachedAdapter
     * @dataProvider provideValidServerConfigData
     */
    public function testCreateAdaptorPassingServerConfig($dsn)
    {
        $adapter = MemcachedAdapter::create($dsn);
        $servers = $adapter->getClient()->getServerList();

        $this->assertCount(1, $servers,
            'A single registered server should exist with \Memcached client.');

        $params = parse_url($dsn);
        $server = array_shift($servers);

        $this->assertSame($server['host'], isset($params['host']) ? $params['host'] : '127.0.0.1',
            'Registered server host with \Memcached client should match expectation.');

        $this->assertSame($server['port'], isset($params['port']) ? $params['port'] : 11211,
            'Registered server port with \Memcached client should match expectation.');
    }

    /**
     * @group memcachedAdapter
     * @dataProvider provideInvalidConnectionDsnSchema
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcached DSN:}
     */
    public function testInvalidConnectionDsnSchema($dsn)
    {
        MemcachedAdapter::create($dsn);
    }

    /**
     * @group memcachedAdapter
     * @dataProvider provideInvalidConnectionDsnQueryWeight
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcached DSN weight:}
     */
    public function testInvalidConnectionDsnQueryWeight($dsn)
    {
        MemcachedAdapter::create($dsn);
    }

    /**
     * @group memcachedAdapter
     * @dataProvider provideInvalidServerOptionsData
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcached option (type|value):([^(]+) \(expects an int(, a bool,)? or a resolvable client constant\)}
     */
    public function testInvalidServerOptions(array $options)
    {
        MemcachedAdapter::create(null, $options);
    }

    public function provideInvalidServerOptionsData()
    {
        return array(
            array(array('OPT_DOES_NOT_EXIST' => 'DISTRIBUTION_CONSISTENT')),
            array(array('OPT_DISTRIBUTION' => 'DISTRIBUTION_DOES_NOT_EXIST')),
            array(array(-1000000 => 'BAD_OPT')),
        );
    }

    /**
     * @group memcachedAdapter
     * @dataProvider provideInvalidServerOptionCombinationsData
     */
    public function testInvalidServerOptionCombinations(array $options)
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM ext for Memcached does not object to invalid option values (silently ignores)');
        }

        $this->assertFalse(MemcachedAdapter::create(static::defaultConnectionServer())->setup(array(), $options),
            'Expects false return when server/option errors encountered.');
    }

    public function provideInvalidServerOptionCombinationsData()
    {
        return array(
            array(array(-10000000 => 'OPT_DISTRIBUTION')),
            array(array('OPT_SERIALIZER' => 'OPT_DISTRIBUTION')),
        );
    }
}
