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

use Symfony\Component\Lock\BlockingSharedLockStoreInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * PostgreSqlStore is a PersistingStoreInterface implementation using
 * PostgreSql advisory locks.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class PostgreSqlStore implements BlockingSharedLockStoreInterface, BlockingStoreInterface
{
    private \PDO $conn;
    private string $dsn;
    private string $username = '';
    private string $password = '';
    private array $connectionOptions = [];
    private static array $storeRegistry = [];

    /**
     * You can either pass an existing database connection as PDO instance or
     * a DSN string that will be used to lazy-connect to the database when the
     * lock is actually used.
     *
     * List of available options:
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @param array $options An associative array of options
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, #[\SensitiveParameter] array $options = [])
    {
        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }

            $this->conn = $connOrDsn;
            $this->checkDriver();
        } else {
            $this->dsn = $connOrDsn;
        }

        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connectionOptions = $options['db_connection_options'] ?? $this->connectionOptions;
    }

    /**
     * @return void
     */
    public function save(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->save($key);

        $lockAcquired = false;

        try {
            $sql = 'SELECT pg_try_advisory_lock(:key)';
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $result = $stmt->execute();

            // Check if lock is acquired
            if (true === $stmt->fetchColumn()) {
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

    /**
     * @return void
     */
    public function saveRead(Key $key)
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->saveRead($key);

        $lockAcquired = false;

        try {
            $sql = 'SELECT pg_try_advisory_lock_shared(:key)';
            $stmt = $this->getConnection()->prepare($sql);

            $stmt->bindValue(':key', $this->getHashedKey($key));
            $result = $stmt->execute();

            // Check if lock is acquired
            if (true === $stmt->fetchColumn()) {
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

    /**
     * @return void
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        // postgresql locks forever.
        // check if lock still exists
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }
    }

    /**
     * @return void
     */
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
        } catch (LockConflictedException) {
            // an other key exists in this ReadLock
        }

        $store->delete($key);
    }

    public function exists(Key $key): bool
    {
        $sql = "SELECT count(*) FROM pg_locks WHERE locktype='advisory' AND objid=:key AND pid=pg_backend_pid()";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':key', $this->getHashedKey($key));
        $result = $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            // connection is locked, check for lock in internal store
            return $this->getInternalStore()->exists($key);
        }

        return false;
    }

    /**
     * @return void
     */
    public function waitAndSave(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->save($key);

        $lockAcquired = false;
        $sql = 'SELECT pg_advisory_lock(:key)';
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $stmt->execute();
            $lockAcquired = true;
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }

        // release lock in case of promotion
        $this->unlockShared($key);
    }

    /**
     * @return void
     */
    public function waitAndSaveRead(Key $key)
    {
        // prevent concurrency within the same connection
        // Internal store does not allow blocking mode, because there is no way to acquire one in a single process
        $this->getInternalStore()->saveRead($key);

        $lockAcquired = false;
        $sql = 'SELECT pg_advisory_lock_shared(:key)';

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $stmt->execute();
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
        while (true) {
            $sql = "SELECT pg_advisory_unlock(objid::bigint) FROM pg_locks WHERE locktype='advisory' AND mode='ExclusiveLock' AND objid=:key AND pid=pg_backend_pid()";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $result = $stmt->execute();

            if (0 === $stmt->rowCount()) {
                break;
            }
        }
    }

    private function unlockShared(Key $key): void
    {
        while (true) {
            $sql = "SELECT pg_advisory_unlock_shared(objid::bigint) FROM pg_locks WHERE locktype='advisory' AND mode='ShareLock' AND objid=:key AND pid=pg_backend_pid()";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue(':key', $this->getHashedKey($key));
            $result = $stmt->execute();

            if (0 === $stmt->rowCount()) {
                break;
            }
        }
    }

    private function getConnection(): \PDO
    {
        if (!isset($this->conn)) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->checkDriver();
        }

        return $this->conn;
    }

    private function checkDriver(): void
    {
        if ('pgsql' !== $driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
        }
    }

    private function getInternalStore(): SharedLockStoreInterface
    {
        $namespace = spl_object_hash($this->getConnection());

        return self::$storeRegistry[$namespace] ??= new InMemoryStore();
    }
}
