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

use Symphony\Component\Lock\Store\RedisStore;
use Symphony\Component\Lock\Store\RetryTillSaveStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RetryTillSaveStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    public function getStore()
    {
        $redis = new \Predis\Client('tcp://'.getenv('REDIS_HOST').':6379');
        try {
            $redis->connect();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        return new RetryTillSaveStore(new RedisStore($redis), 100, 100);
    }
}
