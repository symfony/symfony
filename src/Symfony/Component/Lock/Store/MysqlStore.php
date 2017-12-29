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
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * MysqlStore is a StoreInterface implementation using MySQL/MariaDB GET_LOCK function.
 *
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStore implements StoreInterface
{
    /**
     * @var \PDO|Connection
     */
    private $connection;
    private $waitTimeout;

    /**
     * @param \PDO|Connection $connection
     * @param int             $waitTimeout Time in seconds to wait for a lock to be released. A negative value means infinite.
     */
    public function __construct($connection, $waitTimeout = -1)
    {
        if ($connection instanceof \PDO) {
            if ('mysql' !== $driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                throw new InvalidArgumentException(sprintf('%s requires a "mysql" connection. "%s" given.', __CLASS__, $driver));
            }
        } elseif ($connection instanceof Connection) {
            if ('pdo_mysql' !== $driver = $connection->getDriver()->getName()) {
                throw new InvalidArgumentException(sprintf('%s requires a "pdo_mysql" connection. "%s" given.', __CLASS__, $driver));
            }
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance, "%s" given.', __CLASS__, is_object($connection) ? get_class($connection) : gettype($connection)));
        }

        $this->connection = $connection;
        $this->waitTimeout = $waitTimeout;
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

    private function lock(Key $key, bool $blocking)
    {
        // the lock is maybe already acquired.
        if ($key->hasState(__CLASS__)) {
            return;
        }

        // no timeout for impatient
        $timeout = $blocking ? $this->waitTimeout : 0;

        // Hash the key to guarantee it contains between 1 and 64 characters
        $storedKey = hash('sha256', $key);

        $stmt = $this->connection->prepare('SELECT IF(IS_USED_LOCK(:key) = CONNECTION_ID(), -1, GET_LOCK(:key, :timeout))');
        $stmt->bindValue(':key', $storedKey, \PDO::PARAM_STR);
        $stmt->bindValue(':timeout', $timeout, \PDO::PARAM_INT);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        $stmt->execute();

        // 1:  Lock successful
        // 0:  Already locked by another session
        // -1: Already locked by the same session
        $success = $stmt->fetchColumn();

        if ($blocking && '-1' === $success) {
            throw new LockAcquiringException('Lock already acquired with the same MySQL connection.');
        }

        if ('1' !== $success) {
            throw new LockConflictedException();
        }

        // store the release statement in the state
        $releaseStmt = $this->connection->prepare('DO RELEASE_LOCK(:key)');
        $releaseStmt->bindValue(':key', $storedKey, \PDO::PARAM_STR);

        $key->setState(__CLASS__, $releaseStmt);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        // the GET_LOCK locks forever, until the session terminates.
        $stmt = $this->connection->exec('SET SESSION wait_timeout=GREATEST(@@wait_timeout, :ttl)');
        $stmt->bindValue(':ttl', $ttl, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        if (!$key->hasState(__CLASS__)) {
            return;
        }

        $releaseStmt = $key->getState(__CLASS__);
        $releaseStmt->execute();

        $key->removeState(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        return $key->hasState(__CLASS__);
    }
}
