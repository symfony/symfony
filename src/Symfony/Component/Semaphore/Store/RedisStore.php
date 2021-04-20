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

use Predis\Response\ServerException;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Semaphore\Exception\InvalidArgumentException;
use Symfony\Component\Semaphore\Exception\RuntimeException;
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
    private $redis;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\RedisClusterProxy|\Predis\ClientInterface $redisClient
     */
    public function __construct($redisClient)
    {
        if (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \RedisCluster && !$redisClient instanceof \Predis\ClientInterface && !$redisClient instanceof RedisProxy && !$redisClient instanceof RedisClusterProxy) {
            throw new InvalidArgumentException(sprintf('"%s()" expects parameter 1 to be Redis, RedisArray, RedisCluster, RedisProxy, RedisClusterProxy or Predis\ClientInterface, "%s" given.', __METHOD__, get_debug_type($redisClient)));
        }

        $this->redis = $redisClient;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key, float $ttlInSecond)
    {
        if (0 > $ttlInSecond) {
            throw new InvalidArgumentException("The TTL should be greater than 0, '$ttlInSecond' given.");
        }

        $script = file_get_contents(__DIR__.'/Resources/redis_save.lua');

        $args = [
            $this->getUniqueToken($key),
            time(),
            $ttlInSecond,
            $key->getLimit(),
            $key->getWeight(),
        ];

        try {
            if (!$this->evaluate($script, sprintf('{%s}', $key), $args)) {
                throw new SemaphoreAcquiringException($key, 'the script return false');
            }
        } catch (\Exception $e) {
            throw new SemaphoreAcquiringException($key, 'the script failed', $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, float $ttlInSecond)
    {
        if (0 > $ttlInSecond) {
            throw new InvalidArgumentException("The TTL should be greater than 0, '$ttlInSecond' given.");
        }

        $script = file_get_contents(__DIR__.'/Resources/redis_put_off_expiration.lua');

        try {
            $ret = $this->evaluate($script, sprintf('{%s}', $key), [time() + $ttlInSecond, $this->getUniqueToken($key)]);
        } catch (\Exception $e) {
            throw new SemaphoreAcquiringException($key, 'the script failed', $e);
        }

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
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $script = file_get_contents(__DIR__.'/Resources/redis_delete.lua');

        try {
            $this->evaluate($script, sprintf('{%s}', $key), [$this->getUniqueToken($key)]);
        } catch (\Exception $e) {
            throw new SemaphoreAcquiringException($key, 'the script failed', $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key): bool
    {
        return (bool) $this->redis->zScore(sprintf('{%s}:weight', $key), $this->getUniqueToken($key));
    }

    /**
     * Evaluates a script in the corresponding redis client.
     *
     * @return mixed
     */
    private function evaluate(string $script, string $resource, array $args)
    {
        $sha = sha1($script);

        if ($this->redis instanceof \RedisArray) {
            $client = $this->redis->_instance($this->redis->_target($resource));
        } else {
            $client = $this->redis;
        }

        if (
            $client instanceof \Redis ||
            $client instanceof \RedisCluster ||
            $client instanceof RedisProxy ||
            $client instanceof RedisClusterProxy
        ) {
            $client->clearLastError();
            $result = $client->evalSha($sha, array_merge([$resource], $args), 1);
            $err = $client->getLastError();
            if (false === $result && 0 === strpos($err, 'NOSCRIPT')) {
                $client->clearLastError();
                $result = $client->eval($script, array_merge([$resource], $args), 1);
                $err = $client->getLastError();
            }

            if (null !== $err) {
                throw new RuntimeException($err);
            }

            return $result;
        }

        if ($client instanceof \Predis\ClientInterface) {
            try {
                return $client->evalSha($sha, 1, $resource, ...$args);
            } catch (ServerException $e) {
                if (0 !== strpos($e->getMessage(), 'NOSCRIPT')) {
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }
            try {
                return $client->eval($script, 1, $resource, ...$args);
            } catch (ServerException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        throw new InvalidArgumentException(sprintf('"%s()" expects being initialized with a Redis, RedisArray, RedisCluster or Predis\ClientInterface, "%s" given.', __METHOD__, \is_object($this->redis) ? \get_class($this->redis) : \gettype($this->redis)));
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
