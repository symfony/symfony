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
 */
class PredisStoreTest extends AbstractRedisStoreTest
{
    public static function setupBeforeClass()
    {
        $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected function getRedisConnection()
    {
        $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
        $redis->connect();

        return $redis;
    }
}
