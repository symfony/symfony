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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\Lock\BlockingSharedLockStoreInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * DoctrineDbalPostgreSqlStore is a PersistingStoreInterface implementation using
 * PostgreSql advisory locks with a Doctrine DBAL Connection.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class DoctrineDbalPostgreSqlStore implements BlockingSharedLockStoreInterface, BlockingStoreInterface
{
    private $conn;
    private static $storeRegistry = [];

    /**
     * You can either pass an existing database connection a Doctrine DBAL Connection
     * or a URL that will be used to connect to the database.
     *
     * @param Connection|string $connOrUrl A Connection instance or Doctrine URL
     *
     * @throws InvalidArgumentException When first argument is not Connection nor string
     */
    public function __construct($connOrUrl)
    {
        if ($connOrUrl instanceof Connection) {
            if (!$connOrUrl->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" platform.', __CLASS__, \get_class($connOrUrl->getDatabasePlatform())));
            }
            $this->conn = $connOrUrl;
        } elseif (\is_string($connOrUrl)) {
            if (!class_exists(DriverManager::class)) {
                throw new InvalidArgumentException(sprintf('Failed to parse the DSN "%s". Try running "composer require doctrine/dbal".', $connOrUrl));
            }
            $this->conn = DriverManager::getConnection(['url' => $this->filterDsn($connOrUrl)]);
        } else {
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be "%s" or string, "%s" given.', Connection::class, __METHOD__, get_debug_type($connOrUrl)));
        }
    }

    public function save(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->save($key);

        $lockAcquired = false;

        try {
            $sql = 'SELECT pg_try_advisory_lock(:key)';
            $result = $this->conn->executeQuery($sql, [
                'key' => $this->getHashedKey($key),
            ]);

            // Check if lock is acquired
            if (true === $result->fetchOne()) {
                $key->markUnserializable();
                // release sharedLock in case of promotion
                $this->unlockShared($key);

                $lockAcquired = true;

                return;
            }
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }

        throw new LockConflictedException();
    }

    public function saveRead(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->saveRead($key);

        $lockAcquired = false;

        try {
            $sql = 'SELECT pg_try_advisory_lock_shared(:key)';
            $result = $this->conn->executeQuery($sql, [
                'key' => $this->getHashedKey($key),
            ]);

            // Check if lock is acquired
            if (true === $result->fetchOne()) {
                $key->markUnserializable();
                // release lock in case of demotion
                $this->unlock($key);

                $lockAcquired = true;

                return;
            }
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }

        throw new LockConflictedException();
    }

    public function putOffExpiration(Key $key, float $ttl)
    {
        // postgresql locks forever.
        // check if lock still exists
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }
    }

    public function delete(Key $key)
    {
        // Prevent deleting locks own by an other key in the same connection
        if (!$this->exists($key)) {
            return;
        }

        $this->unlock($key);

        // Prevent deleting Readlocks own by current key AND an other key in the same connection
        $store = $this->getInternalStore();
        try {
            // If lock acquired = there is no other ReadLock
            $store->save($key);
            $this->unlockShared($key);
        } catch (LockConflictedException $e) {
            // an other key exists in this ReadLock
        }

        $store->delete($key);
    }

    public function exists(Key $key)
    {
        $sql = "SELECT count(*) FROM pg_locks WHERE locktype='advisory' AND objid=:key AND pid=pg_backend_pid()";
        $result = $this->conn->executeQuery($sql, [
            'key' => $this->getHashedKey($key),
        ]);

        if ($result->fetchOne() > 0) {
            // connection is locked, check for lock in internal store
            return $this->getInternalStore()->exists($key);
        }

        return false;
    }

    public function waitAndSave(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->save($key);

        $lockAcquired = false;
        $sql = 'SELECT pg_advisory_lock(:key)';
        try {
            $this->conn->executeStatement($sql, [
                'key' => $this->getHashedKey($key),
            ]);
            $lockAcquired = true;
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }

        // release lock in case of promotion
        $this->unlockShared($key);
    }

    public function waitAndSaveRead(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->saveRead($key);

        $lockAcquired = false;
        $sql = 'SELECT pg_advisory_lock_shared(:key)';
        try {
            $this->conn->executeStatement($sql, [
                'key' => $this->getHashedKey($key),
            ]);
            $lockAcquired = true;
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }

        // release lock in case of demotion
        $this->unlock($key);
    }

    /**
     * Returns a hashed version of the key.
     */
    private function getHashedKey(Key $key): int
    {
        return crc32((string) $key);
    }

    private function unlock(Key $key): void
    {
        do {
            $sql = "SELECT pg_advisory_unlock(objid::bigint) FROM pg_locks WHERE locktype='advisory' AND mode='ExclusiveLock' AND objid=:key AND pid=pg_backend_pid()";
            $result = $this->conn->executeQuery($sql, [
                'key' => $this->getHashedKey($key),
            ]);
        } while (0 !== $result->rowCount());
    }

    private function unlockShared(Key $key): void
    {
        do {
            $sql = "SELECT pg_advisory_unlock_shared(objid::bigint) FROM pg_locks WHERE locktype='advisory' AND mode='ShareLock' AND objid=:key AND pid=pg_backend_pid()";
            $result = $this->conn->executeQuery($sql, [
                'key' => $this->getHashedKey($key),
            ]);
        } while (0 !== $result->rowCount());
    }

    /**
     * Check driver and remove scheme extension from DSN.
     * From pgsql+advisory://server/ to pgsql://server/.
     *
     * @throws InvalidArgumentException when driver is not supported
     */
    private function filterDsn(string $dsn): string
    {
        if (!str_contains($dsn, '://')) {
            throw new InvalidArgumentException(sprintf('String "%" is not a valid DSN for Doctrine DBAL.', $dsn));
        }

        [$scheme, $rest] = explode(':', $dsn, 2);
        $driver = strtok($scheme, '+');
        if (!\in_array($driver, ['pgsql', 'postgres', 'postgresql'])) {
            throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
        }

        return sprintf('%s:%s', $driver, $rest);
    }

    private function getInternalStore(): SharedLockStoreInterface
    {
        $namespace = spl_object_hash($this->conn);

        return self::$storeRegistry[$namespace] ?? self::$storeRegistry[$namespace] = new InMemoryStore();
    }
}
