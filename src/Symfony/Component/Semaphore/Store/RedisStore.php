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

use Relay\Cluster as RelayCluster;
use Relay\Relay;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Exception\SemaphoreExpiredException;
use Symfony\Component\Semaphore\Exception\SemaphoreStorageException;
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
    private const NO_SCRIPT_ERROR_MESSAGE_PREFIX = 'NOSCRIPT';

    public function __construct(
        private \Redis|Relay|RelayCluster|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
    ) {
    }

    public function save(Key $key, float $ttlInSecond): void
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

        if (!$this->evaluate($script, \sprintf('{%s}', $key), $args)) {
            throw new SemaphoreAcquiringException($key, 'the script return false');
        }
    }

    public function putOffExpiration(Key $key, float $ttlInSecond): void
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

        $ret = $this->evaluate($script, \sprintf('{%s}', $key), [time() + $ttlInSecond, $this->getUniqueToken($key)]);

        // Occurs when redis has been reset
        if (false === $ret) {
            throw new SemaphoreExpiredException($key, 'the script returns false');
        }

        // Occurs when redis has added an item in the set
        if (0 < $ret) {
            throw new SemaphoreExpiredException($key, 'the script returns a positive number');
        }
    }

    public function delete(Key $key): void
    {
        $script = '
            local key = KEYS[1]
            local weightKey = key .. ":weight"
            local timeKey = key .. ":time"
            local identifier = ARGV[1]

            redis.call("ZREM", timeKey, identifier)
            return redis.call("ZREM", weightKey, identifier)
        ';

        $this->evaluate($script, \sprintf('{%s}', $key), [$this->getUniqueToken($key)]);
    }

    public function exists(Key $key): bool
    {
        return (bool) $this->redis->zScore(\sprintf('{%s}:weight', $key), $this->getUniqueToken($key));
    }

    private function evaluate(string $script, string $resource, array $args): mixed
    {
        $scriptSha = sha1($script);

        if ($this->redis instanceof \Redis || $this->redis instanceof Relay || $this->redis instanceof RelayCluster || $this->redis instanceof \RedisCluster) {
            $this->redis->clearLastError();

            $result = $this->redis->evalSha($scriptSha, array_merge([$resource], $args), 1);
            if (null !== ($err = $this->redis->getLastError()) && str_starts_with($err, self::NO_SCRIPT_ERROR_MESSAGE_PREFIX)) {
                $this->redis->clearLastError();

                if ($this->redis instanceof \RedisCluster || $this->redis instanceof RelayCluster) {
                    foreach ($this->redis->_masters() as $master) {
                        $this->redis->script($master, 'LOAD', $script);
                    }
                } else {
                    $this->redis->script('LOAD', $script);
                }

                if (null !== $err = $this->redis->getLastError()) {
                    throw new SemaphoreStorageException($err);
                }

                $result = $this->redis->evalSha($scriptSha, array_merge([$resource], $args), 1);
            }

            if (null !== $err = $this->redis->getLastError()) {
                throw new SemaphoreStorageException($err);
            }

            return $result;
        }

        if ($this->redis instanceof \RedisArray) {
            $client = $this->redis->_instance($this->redis->_target($resource));
            $client->clearLastError();
            $result = $client->evalSha($scriptSha, array_merge([$resource], $args), 1);
            if (null !== ($err = $client->getLastError()) && str_starts_with($err, self::NO_SCRIPT_ERROR_MESSAGE_PREFIX)) {
                $client->clearLastError();

                $client->script('LOAD', $script);

                if (null !== $err = $client->getLastError()) {
                    throw new SemaphoreStorageException($err);
                }

                $result = $client->evalSha($scriptSha, array_merge([$resource], $args), 1);
            }

            if (null !== $err = $client->getLastError()) {
                throw new SemaphoreStorageException($err);
            }

            return $result;
        }

        if ($this->redis instanceof \Predis\ClientInterface) {
            try {
                return $this->handlePredisError(fn () => $this->redis->evalSha($scriptSha, 1, $resource, ...$args));
            } catch (SemaphoreStorageException $e) {
                // Fallthrough only if we need to load the script
                if (!str_starts_with($e->getMessage(), self::NO_SCRIPT_ERROR_MESSAGE_PREFIX)) {
                    throw $e;
                }
            }

            if ($this->redis->getConnection() instanceof \Predis\Connection\Cluster\ClusterInterface) {
                foreach ($this->redis as $connection) {
                    $this->handlePredisError(fn () => $connection->script('LOAD', $script));
                }
            } else {
                $this->handlePredisError(fn () => $this->redis->script('LOAD', $script));
            }

            return $this->handlePredisError(fn () => $this->redis->evalSha($scriptSha, 1, $resource, ...$args));
        }

        throw new InvalidArgumentException(\sprintf('"%s()" expects being initialized with a Redis, RedisArray, RedisCluster or Predis\ClientInterface, "%s" given.', __METHOD__, get_debug_type($this->redis)));
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function handlePredisError(callable $callback): mixed
    {
        try {
            $result = $callback();
        } catch (\Predis\Response\ServerException $e) {
            throw new SemaphoreStorageException($e->getMessage(), $e->getCode(), $e);
        }

        if ($result instanceof \Predis\Response\Error) {
            throw new SemaphoreStorageException($result->getMessage());
        }

        return $result;
    }
}
