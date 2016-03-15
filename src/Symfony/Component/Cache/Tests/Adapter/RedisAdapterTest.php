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

use Cache\IntegrationTests\CachePoolTest;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @requires extension redis
 */
class RedisAdapterTest extends CachePoolTest
{
    private static $redis;

    public function createCachePool()
    {
        if (defined('HHVM_VERSION')) {
            $this->skippedTests['testDeferredSaveWithoutCommit'] = 'Fails on HHVM';
        }

        return new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__));
    }

    public static function setupBeforeClass()
    {
        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1');
        self::$redis->select(1993);
    }

    public static function tearDownAfterClass()
    {
        self::$redis->flushDB();
        self::$redis->close();
    }
}
