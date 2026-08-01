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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * DoctrineDbalMysqlStore is a PersistingStoreInterface implementation using
 * MySQL advisory locks with a Doctrine DBAL Connection.
 *
 * The advisory locking functions used by this class are supported by both MySQL and MariaDB.
 * It requires MySQL 5.7.5 or MariaDB 10.0.2, the versions that let a session hold several
 * named locks at once. On older servers, acquiring a lock releases the previous one.
 *
 * Shared and read locks are not supported: MySQL has no equivalent of pg_advisory_lock_shared,
 * so Lock::acquireRead() silently gives an exclusive lock instead of failing.
 *
 * The connection should be dedicated to locking: advisory locks are bound to the session, so
 * RELEASE_ALL_LOCKS(), EntityManager::close(), a wait_timeout expiration or a transparent DBAL
 * reconnection release every lock held, and nothing detects it.
 *
 * @author Ryan Pon <me@ryanpon.com>
 */
class DoctrineDbalMysqlStore implements BlockingStoreInterface
{
    private Connection $conn;

    /**
     * You can either pass an existing database connection a Doctrine DBAL Connection
     * or a URL that will be used to connect to the database.
     *
     * @throws InvalidArgumentException When first argument is not Connection nor string
     */
    public function __construct(#[\SensitiveParameter] Connection|string $connOrUrl)
    {
        if ($connOrUrl instanceof Connection) {
            if (!$connOrUrl->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                throw new InvalidArgumentException(\sprintf('The adapter "%s" does not support the "%s" platform.', __CLASS__, $connOrUrl->getDatabasePlatform()::class));
            }
            $this->conn = $connOrUrl;
        } else {
            if (!class_exists(DriverManager::class)) {
                throw new InvalidArgumentException('Failed to parse DSN. Try running "composer require doctrine/dbal".');
            }
            $params = (new DsnParser([
                'db2' => 'ibm_db2',
                'mssql' => 'pdo_sqlsrv',
                'mysql' => 'pdo_mysql',
                'mysql2' => 'pdo_mysql',
                'postgres' => 'pdo_pgsql',
                'postgresql' => 'pdo_pgsql',
                'pgsql' => 'pdo_pgsql',
                'sqlite' => 'pdo_sqlite',
                'sqlite3' => 'pdo_sqlite',
            ]))->parse($this->filterDsn($connOrUrl));

            $config = new Configuration();
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

            $this->conn = DriverManager::getConnection($params, $config);
        }
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
            $this->unlock($key);
        }

        // the internal store is cleared even when the server already dropped the lock,
        // otherwise the resource would stay taken for the rest of the process
        $this->getInternalStore()->delete($key);
    }

    public function exists(Key $key): bool
    {
        $sql = 'SELECT IS_USED_LOCK(:key) = CONNECTION_ID()';
        $result = $this->conn->executeQuery($sql, [
            'key' => $this->getHashedKey($key),
        ])->fetchOne();

        if (1 === (int) $result) {
            return $this->getInternalStore()->exists($key);
        }

        return false;
    }

    private function saveWithTimeout(Key $key, int $timeout): void
    {
        // prevent concurrency within the same connection
        $this->getInternalStore()->save($key);

        $lockAcquired = false;

        try {
            $hashedKey = $this->getHashedKey($key);
            $sql = 'SELECT IF(IS_USED_LOCK(:key) = CONNECTION_ID(), -1, GET_LOCK(:key, :timeout))';
            $maybeResult = $this->conn->executeQuery(
                $sql,
                ['key' => $hashedKey, 'timeout' => $timeout],
                ['timeout' => ParameterType::INTEGER],
            )->fetchOne();
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

            // we only expect to receive 1, 0, -1
            throw new LockAcquiringException('Failed to acquire lock due to mysql error.');
        } finally {
            if (!$lockAcquired) {
                $this->getInternalStore()->delete($key);
            }
        }
    }

    /**
     * Returns a hashed version of the key.
     */
    private function getHashedKey(Key $key): string
    {
        // mysql limits lock name length to 64 chars
        // mariadb compares locks in a case insensitive fashion
        // we mostly get around these limitations by always hashing the key
        return hash('sha256', (string) $key);
    }

    private function unlock(Key $key): void
    {
        $sql = 'DO RELEASE_LOCK(:key)';
        $this->conn->executeQuery($sql, [
            'key' => $this->getHashedKey($key),
        ]);
    }

    /**
     * Check driver and remove scheme extension from DSN.
     * From mysql+advisory://server/ to mysql://server/.
     *
     * @throws InvalidArgumentException when driver is not supported
     */
    private function filterDsn(#[\SensitiveParameter] string $dsn): string
    {
        if (!str_contains($dsn, '://')) {
            throw new InvalidArgumentException('DSN is invalid for Doctrine DBAL.');
        }

        [$scheme, $rest] = explode(':', $dsn, 2);
        $driver = substr($scheme, 0, strpos($scheme, '+') ?: null);
        if (!\in_array($driver, ['mysql', 'mysql2'], true)) {
            throw new InvalidArgumentException(\sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
        }

        return \sprintf('%s:%s', $driver, $rest);
    }

    private function getInternalStore(): SharedLockStoreInterface
    {
        static $storeRegistry = new \WeakMap();

        return $storeRegistry[$this->conn] ??= new InMemoryStore();
    }
}
