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
    private ?\PDO $conn = null;

    private ?string $dsn;

    private array $options;

    private ?int $connectionId = null;

    /** @var bool[] */
    private static array $locksAcquired = [];

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
        $id = $this->getLockId($key);

        if (self::$locksAcquired[$id] ?? false) {
            return;
        }

        $stmt = $this->conn->prepare('SELECT IF(IS_USED_LOCK(:name) = CONNECTION_ID(), -1, GET_LOCK(:name, 0))');
        $stmt->bindValue(':name', self::getLockName($key), \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        // lock acquired
        if (1 === $result) {
            self::$locksAcquired[$id] = true;

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
        $stmt = $this->conn->prepare('DO RELEASE_LOCK(:name)');
        $stmt->bindValue(':name', self::getLockName($key), \PDO::PARAM_STR);
        $stmt->execute();

        unset(self::$locksAcquired[$this->getLockId($key)]);
    }

    public function exists(Key $key): bool
    {
        return self::$locksAcquired[$this->getLockId($key)] ?? false;
    }

    private function getConnection(): \PDO
    {
        if (!$this->conn) {
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

    private function getLockId(Key $key): string
    {
        if (!$this->connectionId) {
            $this->connectionId = $this->getConnection()->query('SELECT CONNECTION_ID()')->fetchColumn();
        }

        return $this->connectionId.'_'.spl_object_id($key);
    }

    private static function getLockName(Key $key): string
    {
        // mysql limits lock name length to 64 chars
        $name = (string) $key;

        return \strlen($name) > 64 ? hash('xxh128', $name) : $name;
    }
}
