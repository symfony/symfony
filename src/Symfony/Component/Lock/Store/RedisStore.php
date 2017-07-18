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

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * RedisStore is a StoreInterface implementation using Redis as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RedisStore implements StoreInterface
{
    private $redis;
    private $initialTtl;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     * @param float                                           $initialTtl  the expiration delay of locks in seconds
     */
    public function __construct($redisClient, $initialTtl = 300.0)
    {
        if (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \RedisCluster && !$redisClient instanceof \Predis\Client) {
            throw new InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, is_object($redisClient) ? get_class($redisClient) : gettype($redisClient)));
        }

        if ($initialTtl <= 0) {
            throw new InvalidArgumentException(sprintf('%s() expects a strictly positive TTL. Got %d.', __METHOD__, $initialTtl));
        }

        $this->redis = $redisClient;
        $this->initialTtl = $initialTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("PEXPIRE", KEYS[1], ARGV[2])
            else
                return redis.call("set", KEYS[1], ARGV[1], "NX", "PX", ARGV[2])
            end
        ';

        $expire = (int) ceil($this->initialTtl * 1000);
        if (!$this->evaluate($script, (string) $key, array($this->getToken($key), $expire))) {
            throw new LockConflictedException();
        }
    }

    public function waitAndSave(Key $key)
    {
        throw new InvalidArgumentException(sprintf('The store "%s" does not supports blocking locks.', get_class($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("PEXPIRE", KEYS[1], ARGV[2])
            else
                return 0
            end
        ';

        $expire = (int) ceil($ttl * 1000);
        if (!$this->evaluate($script, (string) $key, array($this->getToken($key), $expire))) {
            throw new LockConflictedException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        $this->evaluate($script, (string) $key, array($this->getToken($key)));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $this->redis->get((string) $key) === $this->getToken($key);
    }

    /**
     * Evaluates a script in the corresponding redis client.
     *
     * @param string $script
     * @param string $resource
     * @param array  $args
     *
     * @return mixed
     */
    private function evaluate($script, $resource, array $args)
    {
        if ($this->redis instanceof \Redis || $this->redis instanceof \RedisCluster) {
            return $this->redis->eval($script, array_merge(array($resource), $args), 1);
        }

        if ($this->redis instanceof \RedisArray) {
            return $this->redis->_instance($this->redis->_target($resource))->eval($script, array_merge(array($resource), $args), 1);
        }

        if ($this->redis instanceof \Predis\Client) {
            return call_user_func_array(array($this->redis, 'eval'), array_merge(array($script, 1, $resource), $args));
        }

        throw new InvalidArgumentException(sprintf('%s() expects been initialized with a Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, is_object($this->redis) ? get_class($this->redis) : gettype($this->redis)));
    }

    /**
     * Retrieves an unique token for the given key.
     *
     * @param Key $key
     *
     * @return string
     */
    private function getToken(Key $key)
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
