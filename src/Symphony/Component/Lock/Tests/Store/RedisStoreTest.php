<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Tests\Store;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension redis
 */
class RedisStoreTest extends AbstractRedisStoreTest
{
    public static function setupBeforeClass()
    {
        if (!@((new \Redis())->connect(getenv('REDIS_HOST')))) {
            $e = error_get_last();
            self::markTestSkipped($e['message']);
        }
    }

    protected function getRedisConnection()
    {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'));

        return $redis;
    }
}
