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

class MemcachedAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    private static $client;

    public static function setupBeforeClass()
    {
        if (!MemcachedAdapter::isSupported()) {
            self::markTestSkipped('Extension memcached >=2.2.0 required.');
        }

        self::$client = new \Memcached();
        self::$client->addServers(array(array(
            getenv('MEMCACHED_HOST') ?: '127.0.0.1',
            getenv('MEMCACHED_PORT') ?: 11211,
        )));

        parent::setupBeforeClass();
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new MemcachedAdapter(self::$client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testIsSupported()
    {
        $this->assertTrue(MemcachedAdapter::isSupported());
    }
}
