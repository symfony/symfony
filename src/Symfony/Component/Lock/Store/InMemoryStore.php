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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * InMemoryStore is a PersistingStoreInterface implementation using
 * php-array to manage locks.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class InMemoryStore implements SharedLockStoreInterface
{
    private $locks = [];
    private $readLocks = [];

    public function save(Key $key)
    {
        $hashKey = (string) $key;
        $token = $this->getUniqueToken($key);
        if (isset($this->locks[$hashKey])) {
            // already acquired
            if ($this->locks[$hashKey] === $token) {
                return;
            }

            throw new LockConflictedException();
        }

        // check for promotion
        if (isset($this->readLocks[$hashKey][$token]) && 1 === \count($this->readLocks[$hashKey])) {
            unset($this->readLocks[$hashKey]);
            $this->locks[$hashKey] = $token;

            return;
        }

        if (\count($this->readLocks[$hashKey] ?? []) > 0) {
            throw new LockConflictedException();
        }

        $this->locks[$hashKey] = $token;
    }

    public function saveRead(Key $key)
    {
        $hashKey = (string) $key;
        $token = $this->getUniqueToken($key);

        // check if lock is already acquired in read mode
        if (isset($this->readLocks[$hashKey])) {
            $this->readLocks[$hashKey][$token] = true;

            return;
        }

        // check for demotion
        if (isset($this->locks[$hashKey])) {
            if ($this->locks[$hashKey] !== $token) {
                throw new LockConflictedException();
            }

            unset($this->locks[$hashKey]);
        }

        $this->readLocks[$hashKey][$token] = true;
    }

    public function putOffExpiration(Key $key, float $ttl)
    {
        // do nothing, memory locks forever.
    }

    public function delete(Key $key)
    {
        $hashKey = (string) $key;
        $token = $this->getUniqueToken($key);

        unset($this->readLocks[$hashKey][$token]);
        if (($this->locks[$hashKey] ?? null) === $token) {
            unset($this->locks[$hashKey]);
        }
    }

    public function exists(Key $key)
    {
        $hashKey = (string) $key;
        $token = $this->getUniqueToken($key);

        return isset($this->readLocks[$hashKey][$token]) || ($this->locks[$hashKey] ?? null) === $token;
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
