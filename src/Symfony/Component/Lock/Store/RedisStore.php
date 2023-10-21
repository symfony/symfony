<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Predis\Response\ServerException;
use Relay\Relay;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * RedisStore is a PersistingStoreInterface implementation using Redis as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class RedisStore implements SharedLockStoreInterface
{
    use ExpiringStoreTrait;

    private bool $supportTime;

    /**
     * @param float $initialTtl The expiration delay of locks in seconds
     */
    public function __construct(
        private \Redis|Relay|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
        private float $initialTtl = 300.0,
    ) {
        if ($initialTtl <= 0) {
            throw new InvalidTtlException(sprintf('"%s()" expects a strictly positive TTL. Got %d.', __METHOD__, $initialTtl));
        }
    }

    /**
     * @return void
     */
    public function save(Key $key)
    {
        $script = '
            local key = KEYS[1]
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", key).ok == "string" then
                return false
            end

            '.$this->getNowCode().'

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", key, "-inf", now)

            -- is already acquired
            if redis.call("ZSCORE", key, uniqueToken) then
                -- is not WRITE lock and cannot be promoted
                if not redis.call("ZSCORE", key, "__write__") and redis.call("ZCOUNT", key, "-inf", "+inf") > 1  then
                    return false
                end
            elseif redis.call("ZCOUNT", key, "-inf", "+inf") > 0  then
                return false
            end

            redis.call("ZADD", key, now + ttl, uniqueToken)
            redis.call("ZADD", key, now + ttl, "__write__")

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", key, 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", key, maxExpiration)

            return true
        ';

        $key->reduceLifetime($this->initialTtl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($this->initialTtl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    /**
     * @return void
     */
    public function saveRead(Key $key)
    {
        $script = '
            local key = KEYS[1]
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", key).ok == "string" then
                return false
            end

            '.$this->getNowCode().'

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", key, "-inf", now)

            -- lock not already acquired and a WRITE lock exists?
            if not redis.call("ZSCORE", key, uniqueToken) and redis.call("ZSCORE", key, "__write__") then
                return false
            end

            redis.call("ZADD", key, now + ttl, uniqueToken)
            redis.call("ZREM", key, "__write__")

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", key, 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", key, maxExpiration)

            return true
        ';

        $key->reduceLifetime($this->initialTtl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($this->initialTtl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    /**
     * @return void
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        $script = '
            local key = KEYS[1]
            local uniqueToken = ARGV[2]
            local ttl = tonumber(ARGV[3])

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", key).ok == "string" then
                return false
            end

            '.$this->getNowCode().'

            -- lock already acquired acquired?
            if not redis.call("ZSCORE", key, uniqueToken) then
                return false
            end

            redis.call("ZADD", key, now + ttl, uniqueToken)
            -- if the lock is also a WRITE lock, increase the TTL
            if redis.call("ZSCORE", key, "__write__") then
                redis.call("ZADD", key, now + ttl, "__write__")
            end

            -- Extend the TTL of the key
            local maxExpiration = redis.call("ZREVRANGE", key, 0, 0, "WITHSCORES")[2]
            redis.call("PEXPIREAT", key, maxExpiration)

            return true
        ';

        $key->reduceLifetime($ttl);
        if (!$this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key), (int) ceil($ttl * 1000)])) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    /**
     * @return void
     */
    public function delete(Key $key)
    {
        $script = '
            local key = KEYS[1]
            local uniqueToken = ARGV[1]

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", key).ok == "string" then
                return false
            end

            -- lock not already acquired
            if not redis.call("ZSCORE", key, uniqueToken) then
                return false
            end

            redis.call("ZREM", key, uniqueToken)
            redis.call("ZREM", key, "__write__")

            local maxExpiration = redis.call("ZREVRANGE", key, 0, 0, "WITHSCORES")[2]
            if nil ~= maxExpiration then
                redis.call("PEXPIREAT", key, maxExpiration)
            end

            return true
        ';

        $this->evaluate($script, (string) $key, [$this->getUniqueToken($key)]);
    }

    public function exists(Key $key): bool
    {
        $script = '
            local key = KEYS[1]
            local uniqueToken = ARGV[2]

            -- asserts the KEY is compatible with current version (old Symfony <5.2 BC)
            if redis.call("TYPE", key).ok == "string" then
                return false
            end

            '.$this->getNowCode().'

            -- Remove expired values
            redis.call("ZREMRANGEBYSCORE", key, "-inf", now)

            if redis.call("ZSCORE", key, uniqueToken) then
                return true
            end

            return false
        ';

        return (bool) $this->evaluate($script, (string) $key, [microtime(true), $this->getUniqueToken($key)]);
    }

    private function evaluate(string $script, string $resource, array $args): mixed
    {
        if ($this->redis instanceof \Redis || $this->redis instanceof Relay || $this->redis instanceof \RedisCluster) {
            $this->redis->clearLastError();
            $result = $this->redis->eval($script, array_merge([$resource], $args), 1);
            if (null !== $err = $this->redis->getLastError()) {
                throw new LockStorageException($err);
            }

            return $result;
        }

        if ($this->redis instanceof \RedisArray) {
            $client = $this->redis->_instance($this->redis->_target($resource));
            $client->clearLastError();
            $result = $client->eval($script, array_merge([$resource], $args), 1);
            if (null !== $err = $client->getLastError()) {
                throw new LockStorageException($err);
            }

            return $result;
        }

        \assert($this->redis instanceof \Predis\ClientInterface);

        try {
            return $this->redis->eval(...array_merge([$script, 1, $resource], $args));
        } catch (ServerException $e) {
            throw new LockStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    private function getNowCode(): string
    {
        if (!isset($this->supportTime)) {
            // Redis < 5.0 does not support TIME (not deterministic) in script.
            // https://redis.io/commands/eval#replicating-commands-instead-of-scripts
            // This code asserts TIME can be use, otherwise will fallback to a timestamp generated by the PHP process.
            $script = '
                local now = redis.call("TIME")
                redis.call("SET", KEYS[1], "1", "PX", 1)

	            return 1
            ';
            try {
                $this->supportTime = 1 === $this->evaluate($script, 'symfony_check_support_time', []);
            } catch (LockStorageException $e) {
                if (!str_contains($e->getMessage(), 'commands not allowed after non deterministic')) {
                    throw $e;
                }
                $this->supportTime = false;
            }
        }

        if ($this->supportTime) {
            return '
                local now = redis.call("TIME")
                now = now[1] * 1000 + math.floor(now[2] / 1000)
            ';
        }

        return '
            local now = tonumber(ARGV[1])
            now = math.floor(now * 1000)
        ';
    }
}
