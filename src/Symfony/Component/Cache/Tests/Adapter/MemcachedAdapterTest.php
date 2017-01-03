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

use Symfony\Component\Cache\Adapter\Client\MemcachedClient;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class MemcachedAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    private static $client;

    public static function defaultConnectionServers()
    {
        return array(
            sprintf('memcached://%s:%d', getenv('MEMCACHED_HOST') ?: '127.0.0.1', getenv('MEMCACHED_PORT') ?: 11211),
        );
    }

    public static function setupBeforeClass()
    {
        if (!MemcachedClient::isSupported()) {
            self::markTestSkipped('Memcached extension >= 2.2.0 required for test.');
        }

        self::$client = MemcachedClient::create(static::defaultConnectionServers());

        parent::setupBeforeClass();
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new MemcachedAdapter(self::$client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testCreateConnection()
    {
        $servers = static::defaultConnectionServers();
        $options = array('compression' => true);

        $expect = array(array(
            'host' => parse_url($servers[0], PHP_URL_HOST),
            'port' => parse_url($servers[0], PHP_URL_PORT),
            'type' => 'TCP',
        ));

        $client = MemcachedAdapter::createConnection($servers, $options);

        $this->assertSame($expect, $client->getServerList());
        $this->assertTrue($client->getOption(\Memcached::OPT_COMPRESSION));
    }
}
