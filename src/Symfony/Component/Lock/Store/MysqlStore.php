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

use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * MysqlStore is a PersistingStoreInterface implementation using advisory locks.
 *
 * The advisory locking functions used by this class are supported by both MySQL and MariaDB.
 * It requires MySQL 5.7.5 or MariaDB 10.0.2, the versions that let a session hold several
 * named locks at once. On older servers, acquiring a lock releases the previous one.
 *
 * Shared and read locks are not supported: MySQL has no equivalent of pg_advisory_lock_shared,
 * so Lock::acquireRead() silently gives an exclusive lock instead of failing.
 *
 * The connection should be dedicated to locking: advisory locks are bound to the session, so
 * RELEASE_ALL_LOCKS(), EntityManager::close(), a wait_timeout expiration or a transparent
 * reconnection release every lock held, and nothing detects it.
 *
 * @author Ryan Pon <me@ryanpon.com>
 * @author rtek
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStore implements BlockingStoreInterface
{
    private \PDO $conn;

    private string $dsn;

    private array $options;

    private \PDOStatement $saveStmt;

    private \PDOStatement $existsStmt;

    private \PDOStatement $deleteStmt;

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
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When driver is not mysql
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, #[\SensitiveParameter] array $options = [])
    {
        if ($connOrDsn instanceof \PDO) {
            $this->conn = $connOrDsn;
            $this->assertMysqlDriver();
            if (\PDO::ERRMODE_EXCEPTION !== $this->conn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(\sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }
        } else {
            $this->dsn = $connOrDsn;
        }

        $this->options = $options;
    }

    public function save(Key $key): void
    {
        // timeout 0 means no waiting
        $this->saveWithTimeout($key, 0);
    }

    public function waitAndSave(Key $key): void
    {
        // timeout -1 will wait forever
        $this->saveWithTimeout($key, -1);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        // mysql locks forever.
        // check if lock still exists
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }
    }

    public function delete(Key $key): void
    {
        // prevent deleting locks owned by another key in the same connection
        if ($this->exists($key)) {
            $stmt = $this->deleteStmt ??
                $this->deleteStmt = $this->getConnection()->prepare('DO RELEASE_LOCK(:name)');

            $stmt->execute(['name' => self::getHashedKey($key)]);
        }

        // the internal store is cleared even when the server already dropped the lock,
        // otherwise the resource would stay taken for the rest of the process
        $this->getInternalStore()->delete($key);
    }

    public function exists(Key $key): bool
    {
        $stmt = $this->existsStmt ??
            $this->existsStmt = $this->getConnection()->prepare('SELECT IS_USED_LOCK(:name) = CONNECTION_ID()');

        $stmt->execute(['name' => self::getHashedKey($key)]);
        $result = (int) $stmt->fetchColumn();

        if (1 === $result) {
            return $this->getInternalStore()->exists($key);
        }

        return false;
    }

    private function saveWithTimeout(Key $key, int $timeout): void
    {
        $this->getInternalStore()->save($key);

        $lockAcquired = false;

        try {
            // the lock name is bound twice because native prepared statements reject a repeated placeholder
            $stmt = $this->saveStmt ??
                $this->saveStmt = $this->getConnection()->prepare('SELECT IF(IS_USED_LOCK(:name1) = CONNECTION_ID(), -1, GET_LOCK(:name2, :timeout))');
            $name = self::getHashedKey($key);
            $stmt->bindValue('name1', $name);
            $stmt->bindValue('name2', $name);
            $stmt->bindValue('timeout', $timeout, \PDO::PARAM_INT);
            $stmt->execute();
            $maybeResult = $stmt->fetchColumn();
            if (null === $maybeResult) {
                throw new LockAcquiringException('Failed to acquire lock due to mysql error.');
            }

            $result = (int) $maybeResult;

            // 1 means lock was acquired, -1 means it was already acquired on this conn
            if (1 === $result || -1 === $result) {
                $key->markUnserializable();
                $lockAcquired = true;

                return;
            }

            if (0 === $result) {
                throw new LockConflictedException('Lock already acquired by other connection.');
            }

            // We only expect to receive 1, 0, -1
            throw new LockAcquiringException('Failed to acquire lock due to mysql error.');
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }
    }

    private function getConnection(): \PDO
    {
        if (!isset($this->conn)) {
            $this->conn = new \PDO(
                $this->dsn,
                $this->options['db_username'] ?? null,
                $this->options['db_password'] ?? null,
                $this->options['db_connection_options'] ?? null
            );
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->assertMysqlDriver();
        }

        return $this->conn;
    }

    private function assertMysqlDriver(): void
    {
        if ('mysql' !== $driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new InvalidArgumentException(\sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
        }
    }

    private static function getHashedKey(Key $key): string
    {
        // mysql limits lock name length to 64 chars
        // mariadb compares locks in a case insensitive fashion
        // we mostly get around these limitations by always hashing the key
        return hash('sha256', (string) $key);
    }

    private function getInternalStore(): SharedLockStoreInterface
    {
        static $storeRegistry = new \WeakMap();

        return $storeRegistry[$this->getConnection()] ??= new InMemoryStore();
    }
}
