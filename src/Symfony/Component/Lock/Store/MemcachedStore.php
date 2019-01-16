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
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * MemcachedStore is a StoreInterface implementation using Memcached as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MemcachedStore implements StoreInterface
{
    private $memcached;
    private $initialTtl;
    /** @var bool */
    private $useExtendedReturn;

    public static function isSupported()
    {
        return \extension_loaded('memcached');
    }

    /**
     * @param \Memcached $memcached
     * @param int        $initialTtl the expiration delay of locks in seconds
     */
    public function __construct(\Memcached $memcached, $initialTtl = 300)
    {
        if (!static::isSupported()) {
            throw new InvalidArgumentException('Memcached extension is required');
        }

        if ($initialTtl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a strictly positive TTL. Got %d.', __METHOD__, $initialTtl));
        }

        $this->memcached = $memcached;
        $this->initialTtl = $initialTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $token = $this->getToken($key);
        $key->reduceLifetime($this->initialTtl);
        if (!$this->memcached->add((string) $key, $token, (int) ceil($this->initialTtl))) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }
    }

    public function waitAndSave(Key $key)
    {
        throw new InvalidArgumentException(sprintf('The store "%s" does not supports blocking locks.', \get_class($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a TTL greater or equals to 1. Got %s.', __METHOD__, $ttl));
        }

        // Interface defines a float value but Store required an integer.
        $ttl = (int) ceil($ttl);

        $token = $this->getToken($key);

        list($value, $cas) = $this->getValueAndCas($key);

        $key->reduceLifetime($ttl);
        // Could happens when we ask a putOff after a timeout but in luck nobody steal the lock
        if (\Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
            if ($this->memcached->add((string) $key, $token, $ttl)) {
                return;
            }

            // no luck, with concurrency, someone else acquire the lock
            throw new LockConflictedException();
        }

        // Someone else steal the lock
        if ($value !== $token) {
            throw new LockConflictedException();
        }

        if (!$this->memcached->cas($cas, (string) $key, $token, $ttl)) {
            throw new LockConflictedException();
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $token = $this->getToken($key);

        list($value, $cas) = $this->getValueAndCas($key);

        if ($value !== $token) {
            // we are not the owner of the lock. Nothing to do.
            return;
        }

        // To avoid concurrency in deletion, the trick is to extends the TTL then deleting the key
        if (!$this->memcached->cas($cas, (string) $key, $token, 2)) {
            // Someone steal our lock. It does not belongs to us anymore. Nothing to do.
            return;
        }

        // Now, we are the owner of the lock for 2 more seconds, we can delete it.
        $this->memcached->delete((string) $key);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $this->memcached->get((string) $key) === $this->getToken($key);
    }

    /**
     * Retrieve an unique token for the given key.
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

    private function getValueAndCas(Key $key)
    {
        if (null === $this->useExtendedReturn) {
            $this->useExtendedReturn = version_compare(phpversion('memcached'), '2.9.9', '>');
        }

        if ($this->useExtendedReturn) {
            $extendedReturn = $this->memcached->get((string) $key, null, \Memcached::GET_EXTENDED);
            if (\Memcached::GET_ERROR_RETURN_VALUE === $extendedReturn) {
                return [$extendedReturn, 0.0];
            }

            return [$extendedReturn['value'], $extendedReturn['cas']];
        }

        $cas = 0.0;
        $value = $this->memcached->get((string) $key, null, $cas);

        return [$value, $cas];
    }
}
