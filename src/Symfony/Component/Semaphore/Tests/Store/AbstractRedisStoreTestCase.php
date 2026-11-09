<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use Relay\Relay;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
abstract class AbstractRedisStoreTestCase extends AbstractStoreTestCase
{
    abstract protected function getRedisConnection(): \Redis|Relay|\RedisArray|\RedisCluster|\Predis\ClientInterface;

    public function getStore(): PersistingStoreInterface
    {
        return new RedisStore($this->getRedisConnection());
    }

    public function testSaveTwicePutsOffExpiration()
    {
        $redis = $this->getRedisConnection();
        $store = new RedisStore($redis);
        $key = new Key(uniqid(__METHOD__, true), 1);

        $store->save($key, 10);
        $store->save($key, 100);

        $this->assertGreaterThan(microtime(true) + 90, (float) $redis->zScore(\sprintf('{%s}:time', $key), $key->getState(RedisStore::class)));
        $this->assertGreaterThan(100, $redis->ttl(\sprintf('{%s}:weight', $key)));

        $store->delete($key);
    }

    public function testExpirationIsNotTruncatedToTheSecond()
    {
        $redis = $this->getRedisConnection();
        $store = new RedisStore($redis);
        $key = new Key(uniqid(__METHOD__, true), 1);

        // floats are sent to Redis with 14 significant digits
        $before = microtime(true) - 0.001;
        $store->save($key, 10);
        $this->assertGreaterThanOrEqual($before + 10, (float) $redis->zScore(\sprintf('{%s}:time', $key), $key->getState(RedisStore::class)));

        $before = microtime(true) - 0.001;
        $store->putOffExpiration($key, 20);
        $this->assertGreaterThanOrEqual($before + 20, (float) $redis->zScore(\sprintf('{%s}:time', $key), $key->getState(RedisStore::class)));

        $store->delete($key);
    }
}
