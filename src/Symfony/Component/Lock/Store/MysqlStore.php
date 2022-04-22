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
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author rtek
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStore implements PersistingStoreInterface
{
    private \PDO $conn;

    private string $dsn;

    private array $options;

    private int $connectionId;

    private \PDOStatement $saveStmt;

    private \PDOStatement $existsStmt;

    private \PDOStatement $deleteStmt;

    public function __construct(\PDO|string $connOrDsn, array $options = [])
    {
        if ($connOrDsn instanceof \PDO) {
            $this->conn = $connOrDsn;
            $this->assertMysqlDriver();
            if (\PDO::ERRMODE_EXCEPTION !== $this->conn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }
        } else {
            $this->dsn = $connOrDsn;
        }

        $this->options = $options;
    }

    public function save(Key $key): void
    {
        if ($this->exists($key)) {
            return;
        }

        $stmt = $this->saveStmt ??
            $this->saveStmt = $this->getConnection()->prepare('SELECT IF(IS_USED_LOCK(:name) = CONNECTION_ID(), -1, GET_LOCK(:name, 0))');

        $name = self::getLockName($key);
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetchColumn();

        // lock acquired
        if (1 === $result) {
            $key->setState($this->getStateKey($key), $name);

            return;
        }

        if (0 === $result) {
            throw new LockConflictedException('Lock already acquired by other connection.');
        }

        if (-1 === $result) {
            throw new LockConflictedException('Lock already acquired by this connection.');
        }

        throw new LockAcquiringException('Failed to acquire lock due to mysql error.');
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        // noop - GET_LOCK() does not have a ttl
    }

    public function delete(Key $key): void
    {
        $stmt = $this->deleteStmt ??
            $this->deleteStmt = $this->getConnection()->prepare('DO RELEASE_LOCK(:name)');

        $stmt->execute(['name' => self::getLockName($key)]);

        $key->removeState($this->getStateKey($key));
    }

    public function exists(Key $key): bool
    {
        $stateKey = $this->getStateKey($key);
        if (!$key->hasState($stateKey)) {
            return false;
        }

        $stmt = $this->existsStmt ??
            $this->existsStmt = $this->getConnection()->prepare('SELECT IS_USED_LOCK(:name) = CONNECTION_ID()');

        $stmt->execute(['name' => self::getLockName($key)]);
        $result = $stmt->fetchColumn();

        if (1 !== $result) {
            $key->removeState($stateKey);

            return false;
        }

        return true;
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
            throw new InvalidArgumentException(sprintf('The adapter "%s" does not support the "%s" driver.', __CLASS__, $driver));
        }
    }

    private function getStateKey(Key $key): string
    {
        if (!isset($this->connectionId)) {
            $this->connectionId = $this->getConnection()->query('SELECT CONNECTION_ID()')->fetchColumn();
        }

        return __CLASS__.'_'.$this->connectionId;
    }

    private static function getLockName(Key $key): string
    {
        // mysql limits lock name length to 64 chars
        $name = (string) $key;

        return \strlen($name) > 64 ? hash('xxh128', $name) : $name;
    }
}
