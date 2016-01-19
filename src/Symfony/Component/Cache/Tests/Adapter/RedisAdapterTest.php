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

class RedisAdapterTest extends CachePoolTest
{
    /**
     * @var \Redis
     */
    private static $redis;

    public function createCachePool()
    {
        return new RedisAdapter($this->getRedis(), __CLASS__);
    }

    private function getRedis()
    {
        if (self::$redis) {
            return self::$redis;
        }

        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1');
        self::$redis->select(1993);

        return self::$redis;
    }

    public static function tearDownAfterClass()
    {
        self::$redis->flushDB();
        self::$redis->close();
    }
}
