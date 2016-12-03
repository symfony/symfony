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

/**
 * @group time-sensitive
 */
class MemcachedAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    );

    /** @var \Memcached */
    protected static $memcachedClient;

    public static function setupBeforeClass()
    {
        if (!extension_loaded('memcached')) {
            throw new \PHPUnit_Framework_SkippedTestError('Extension memcached required.');
        }

        $memcachedHost = getenv('MEMCACHED_HOST');

        $client = new \Memcached();

        $client->addServers([
            [$memcachedHost, 11211],
        ]);

        static::$memcachedClient = $client;
    }

    public static function tearDownAfterClass()
    {
        static::$memcachedClient->quit();
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new MemcachedAdapter(static::$memcachedClient, '', $defaultLifetime);
    }
}
