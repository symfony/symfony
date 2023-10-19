<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Store;

use Relay\Relay;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;

/**
 * RedisStore is a PersistingStoreInterface implementation using Redis as store engine.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RedisStore implements PersistingStoreInterface
{
    public function __construct(
        private \Redis|Relay|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
    ) {
    }

    /**
     * @return void
     */
    public function save(Key $key, float $ttlInSecond)
    {
        if (0 > $ttlInSecond) {
            throw new InvalidArgumentException("The TTL should be greater than 0, '$ttlInSecond' given.");
        }

        $script = '
            local key = KEYS[1]
            local weightKey = key .. ":weight"
            local timeKey = key .. ":time"
            local identifier = ARGV[1]
            local now = tonumber(ARGV[2])
            local ttlInSecond = tonumber(ARGV[3])
            local limit = tonumber(ARGV[4])
            local weight = tonumber(ARGV[5])

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", timeKey, "-inf", now)
            redis.call("ZINTERSTORE", weightKey, 2, weightKey, timeKey, "WEIGHTS", 1, 0)

            -- Semaphore already acquired?
            if redis.call("ZSCORE", timeKey, identifier) then
                return true
            end

            -- Try to get a semaphore
            local semaphores = redis.call("ZRANGE", weightKey, 0, -1, "WITHSCORES")
            local count = 0

            for i = 1, #semaphores, 2 do
                count = count + semaphores[i+1]
            end

            -- Could we get the semaphore ?
            if count + weight > limit then
                return false
            end

            -- Acquire the semaphore
            redis.call("ZADD", timeKey, now + ttlInSecond, identifier)
            redis.call("ZADD", weightKey, weight, identifier)

            -- Extend the TTL
            local maxExpiration = redis.call("ZREVRANGE", timeKey, 0, 0, "WITHSCORES")[2]
            redis.call("EXPIREAT", weightKey, maxExpiration + 10)
            redis.call("EXPIREAT", timeKey, maxExpiration + 10)

            return true
        ';

        $args = [
            $this->getUniqueToken($key),
            time(),
            $ttlInSecond,
            $key->getLimit(),
            $key->getWeight(),
        ];

        if (!$this->evaluate($script, sprintf('{%s}', $key), $args)) {
            throw new SemaphoreAcquiringException($key, 'the script return false');
        }
    }

    /**
     * @return void
     */
    public function putOffExpiration(Key $key, float $ttlInSecond)
    {
        if (0 > $ttlInSecond) {
            throw new InvalidArgumentException("The TTL should be greater than 0, '$ttlInSecond' given.");
        }

        $script = '
            local key = KEYS[1]
            local weightKey = key .. ":weight"
            local timeKey = key .. ":time"

            local added = redis.call("ZADD", timeKey, ARGV[1], ARGV[2])
            if added == 1 then
                redis.call("ZREM", timeKey, ARGV[2])
                redis.call("ZREM", weightKey, ARGV[2])
            end

            -- Extend the TTL
            local maxExpiration = redis.call("ZREVRANGE", timeKey, 0, 0, "WITHSCORES")[2]
            if nil == maxExpiration then
                return 1
            end

            redis.call("EXPIREAT", weightKey, maxExpiration + 10)
            redis.call("EXPIREAT", timeKey, maxExpiration + 10)

            return added
        ';

        $ret = $this->evaluate($script, sprintf('{%s}', $key), [time() + $ttlInSecond, $this->getUniqueToken($key)]);

        // Occurs when redis has been reset
        if (false === $ret) {
            throw new SemaphoreExpiredException($key, 'the script returns false');
        }

        // Occurs when redis has added an item in the set
        if (0 < $ret) {
            throw new SemaphoreExpiredException($key, 'the script returns a positive number');
        }
    }

    /**
     * @return void
     */
    public function delete(Key $key)
    {
        $script = '
            local key = KEYS[1]
            local weightKey = key .. ":weight"
            local timeKey = key .. ":time"
            local identifier = ARGV[1]

            redis.call("ZREM", timeKey, identifier)
            return redis.call("ZREM", weightKey, identifier)
        ';

        $this->evaluate($script, sprintf('{%s}', $key), [$this->getUniqueToken($key)]);
    }

    public function exists(Key $key): bool
    {
        return (bool) $this->redis->zScore(sprintf('{%s}:weight', $key), $this->getUniqueToken($key));
    }

    private function evaluate(string $script, string $resource, array $args): mixed
    {
        if ($this->redis instanceof \Redis || $this->redis instanceof Relay || $this->redis instanceof \RedisCluster) {
            return $this->redis->eval($script, array_merge([$resource], $args), 1);
        }

        if ($this->redis instanceof \RedisArray) {
            return $this->redis->_instance($this->redis->_target($resource))->eval($script, array_merge([$resource], $args), 1);
        }

        if ($this->redis instanceof \Predis\ClientInterface) {
            return $this->redis->eval(...array_merge([$script, 1, $resource], $args));
        }

        throw new InvalidArgumentException(sprintf('"%s()" expects being initialized with a Redis, RedisArray, RedisCluster or Predis\ClientInterface, "%s" given.', __METHOD__, get_debug_type($this->redis)));
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
