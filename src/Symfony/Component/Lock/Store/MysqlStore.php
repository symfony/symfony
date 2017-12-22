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
use Symfony\Component\Lock\StoreInterface;

/**
 * MysqlStore is a StoreInterface implementation using MySQL/MariaDB GET_LOCK function.
 *
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStore implements StoreInterface
{
    private $dsn;
    private $username;
    private $password;
    private $options;

    private $waitTimeout;

    /**
     * List of available options:
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: array()]
     *  * wait_timeout: Time in seconds to wait for a lock to be released. A negative value means infinite. [default: -1].
     *
     * @param string $dsn     The connection DSN string
     * @param array  $options configuration options
     */
    public function __construct($dsn, array $options)
    {
        $this->dsn = $dsn;
        $this->username = $options['db_username'] ?? '';
        $this->password = $options['db_password'] ?? '';
        $this->options = $options['db_connection_options'] ?? array();
        $this->waitTimeout = $options['wait_timeout'] ?? -1;
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
        // The lock is maybe already acquired.
        if ($key->hasState(__CLASS__)) {
            return;
        }

        // no timeout for impatient
        $timeout = $blocking ? $this->waitTimeout : 0;

        $connection = new \PDO($this->dsn, $this->username, $this->password, $this->options);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $stmt = $connection->prepare('SELECT GET_LOCK(:key, :timeout)');
        $stmt->bindValue(':key', hash('sha256', $key), \PDO::PARAM_STR);
        $stmt->bindValue(':timeout', $timeout, \PDO::PARAM_INT);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        $stmt->execute();
        $success = $stmt->fetchColumn();

        if ('0' === $success) {
            throw new LockConflictedException();
        }

        // store the release statement in the state
        $releaseStmt = $connection->prepare('SELECT RELEASE_LOCK(:key)');
        $releaseStmt->bindValue(':key', hash('sha256', $key), \PDO::PARAM_STR);

        $key->setState(__CLASS__, $releaseStmt);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        // do nothing, the GET_LOCK locks forever, until the session terminates.
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

        // Close the connection.
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
