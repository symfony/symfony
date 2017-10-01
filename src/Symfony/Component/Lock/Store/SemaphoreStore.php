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
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * SemaphoreStore is a StoreInterface implementation using Semaphore as store engine.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class SemaphoreStore implements StoreInterface
{
    /**
     * Returns whether or not the store is supported.
     *
     * @param bool|null $blocking when not null, checked again the blocking mode
     *
     * @return bool
     *
     * @internal
     */
    public static function isSupported($blocking = null)
    {
        if (!extension_loaded('sysvsem')) {
            return false;
        }

        if (false === $blocking && \PHP_VERSION_ID < 50601) {
            return false;
        }

        return true;
    }

    public function __construct()
    {
        if (!static::isSupported()) {
            throw new InvalidArgumentException('Semaphore extension (sysvsem) is required');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $this->lock($key, false);
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndSave(Key $key)
    {
        $this->lock($key, true);
    }

    private function lock(Key $key, $blocking)
    {
        if ($key->hasState(__CLASS__)) {
            return;
        }

        $resource = sem_get(crc32($key));

        if (\PHP_VERSION_ID < 50601) {
            if (!$blocking) {
                throw new NotSupportedException(sprintf('The store "%s" does not supports non blocking locks.', get_class($this)));
            }

            $acquired = sem_acquire($resource);
        } else {
            $acquired = sem_acquire($resource, !$blocking);
        }

        if (!$acquired) {
            throw new LockConflictedException();
        }

        $key->setState(__CLASS__, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        // The lock is maybe not acquired.
        if (!$key->hasState(__CLASS__)) {
            return;
        }

        $resource = $key->getState(__CLASS__);

        sem_release($resource);

        $key->removeState(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        // do nothing, the semaphore locks forever.
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $key->hasState(__CLASS__);
    }
}
