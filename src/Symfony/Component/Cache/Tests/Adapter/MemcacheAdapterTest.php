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

use Symfony\Component\Cache\Adapter\MemcacheAdapter;

class MemcacheAdapterTest extends AbstractMemcacheAdapterTest
{
    protected static $extension = 'memcache';

    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();

        if (!version_compare(phpversion('memcache'), '3.0.8', '>')) {
            self::markTestSkipped(sprintf('Extension %s required must be > 3.0.8.', static::$extension));
        }

        self::$client = MemcacheAdapter::create(static::defaultConnectionServer())->getClient();
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new MemcacheAdapter(self::$client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    /**
     * @group memcacheAdapter
     */
    public function testCreateAdaptor()
    {
        $adapter = MemcacheAdapter::create();
        $memcache = $adapter->getClient();

        $this->assertInstanceOf(MemcacheAdapter::class, $adapter,
            'Adapter created should be instance of MemcacheAdapter.');

        $this->assertInstanceOf(\Memcache::class, $memcache,
            'Client created should be instance of Memcache.');

        $this->assertTrue($adapter->setup(array(static::defaultConnectionServer())),
            'Expects true return when no server/option errors.');

        $this->assertSame(1, $memcache->getServerStatus('127.0.0.1', 11211),
            'A single registered servers should exist with Memcache client.');
    }

    /**
     * @group memcacheAdapter
     * @dataProvider provideValidServerConfigData
     */
    public function testCreateAdaptorPassingServerConfig($dsn)
    {
        $adapter = MemcacheAdapter::create($dsn);
        $params = parse_url($dsn);

        $this->assertSame(1, $adapter->getClient()->getServerStatus(
                isset($params['host']) ? $params['host'] : '127.0.0.1',
                isset($params['port']) ? $params['port'] : 11211)
        );
    }

    /**
     * @group memcacheAdapter
     * @dataProvider provideInvalidConnectionDsnSchema
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcache DSN:}
     */
    public function testInvalidConnectionDsnSchema($dsn)
    {
        MemcacheAdapter::create($dsn);
    }

    /**
     * @group memcacheAdapter
     * @dataProvider provideInvalidConnectionDsnHostOrPort
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcache DSN( host)?:}
     */
    public function testInvalidConnectionDsnHostOrPort($dsn)
    {
        MemcacheAdapter::create($dsn);
    }

    /**
     * @group memcacheAdapter
     * @dataProvider provideInvalidConnectionDsnQueryWeight
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp {Invalid memcache DSN weight:}
     */
    public function testInvalidConnectionDsnQueryWeight($dsn)
    {
        MemcacheAdapter::create($dsn);
    }
}
