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

use Symfony\Component\Cache\Adapter\RedisAdapter;

abstract class AbstractRedisAdapterTest extends AdapterTestCase
{
    protected static $redis;

    public function createCachePool($defaultLifetime = 0)
    {
        if (defined('HHVM_VERSION')) {
            $this->skippedTests['testDeferredSaveWithoutCommit'] = 'Fails on HHVM';
        }

        return new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public static function setupBeforeClass()
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('Extension redis required.');
        }
        if (!@((new \Redis())->connect('127.0.0.1'))) {
            $e = error_get_last();
            self::markTestSkipped($e['message']);
        }
    }

    public static function tearDownAfterClass()
    {
        self::$redis->flushDB();
        self::$redis = null;
    }
}
