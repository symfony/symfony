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
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * PdoStore is a StoreInterface implementation using a PDO connection.
 *
 * Lock metadata are stored in a table. You can use createTable() to initialize
 * a correctly defined table.

 * CAUTION: This store relies on all client and server nodes to have
 * synchronized clocks for lock expiry to occur at the correct time.
 * To ensure locks don't expire prematurely; the TTLs should be set with enough
 * extra time to account for any clock drift between nodes.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class PdoStore implements StoreInterface
{
    private $conn;
    private $dsn;
    private $driver;
    private $table = 'lock_keys';
    private $idCol = 'key_id';
    private $tokenCol = 'key_token';
    private $expirationCol = 'key_expiration';
    private $username = '';
    private $password = '';
    private $connectionOptions = array();
    private $gcProbability;
    private $initialTtl;

    /**
     * You can either pass an existing database connection as PDO instance or
     * a Doctrine DBAL Connection or a DSN string that will be used to
     * lazy-connect to the database when the lock is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: lock_keys]
     *  * db_id_col: The column where to store the lock key [default: key_id]
     *  * db_token_col: The column where to store the lock token [default: key_token]
     *  * db_expiration_col: The column where to store the expiration [default: key_expiration]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: array()]
     *
     * @param \PDO|Connection|string $connOrDsn     A \PDO or Connection instance or DSN string or null
     * @param array                  $options       An associative array of options
     * @param float                  $gcProbability Probability expressed as floating number between 0 and 1 to clean old locks
     * @param int                    $initialTtl    The expiration delay of locks in seconds
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     * @throws InvalidArgumentException When the initial ttl is not valid
     */
    public function __construct($connOrDsn, array $options = array(), float $gcProbability = 0.01, int $initialTtl = 300)
    {
        if ($gcProbability < 0 || $gcProbability > 1) {
            throw new InvalidArgumentException(sprintf('"%s" requires gcProbability between 0 and 1, "%f" given.', __METHOD__, $gcProbability));
        }
        if ($initialTtl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a strictly positive TTL, "%d" given.', __METHOD__, $initialTtl));
        }

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', __METHOD__));
            }

            $this->conn = $connOrDsn;
        } elseif ($connOrDsn instanceof Connection) {
            $this->conn = $connOrDsn;
        } elseif (\is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, \is_object($connOrDsn) ? \get_class($connOrDsn) : \gettype($connOrDsn)));
        }

        $this->table = $options['db_table'] ?? $this->table;
        $this->idCol = $options['db_id_col'] ?? $this->idCol;
        $this->tokenCol = $options['db_token_col'] ?? $this->tokenCol;
        $this->expirationCol = $options['db_expiration_col'] ?? $this->expirationCol;
        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connectionOptions = $options['db_connection_options'] ?? $this->connectionOptions;

        $this->gcProbability = $gcProbability;
        $this->initialTtl = $initialTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $key->reduceLifetime($this->initialTtl);

        $sql = "INSERT INTO $this->table ($this->idCol, $this->tokenCol, $this->expirationCol) VALUES (:id, :token, {$this->getCurrentTimestampStatement()} + $this->initialTtl)";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));

        try {
            $stmt->execute();
            if ($key->isExpired()) {
                throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $key));
            }

            return;
        } catch (DBALException $e) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        } catch (\PDOException $e) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }

        if ($this->gcProbability > 0 && (1.0 === $this->gcProbability || (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) <= $this->gcProbability)) {
            $this->prune();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function waitAndSave(Key $key)
    {
        throw new NotSupportedException(sprintf('The store "%s" does not supports blocking locks.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(sprintf('%s() expects a TTL greater or equals to 1 second. Got %s.', __METHOD__, $ttl));
        }

        $key->reduceLifetime($ttl);

        $sql = "UPDATE $this->table SET $this->expirationCol = {$this->getCurrentTimestampStatement()} + $ttl, $this->tokenCol = :token WHERE $this->idCol = :id AND ($this->tokenCol = :token OR $this->expirationCol <= {$this->getCurrentTimestampStatement()})";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $stmt->execute();

        // If this method is called twice in the same second, the row wouldn't be updated. We have to call exists to know if we are the owner
        if (!$stmt->rowCount() && !$this->exists($key)) {
            throw new LockConflictedException();
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to put off the expiration of the "%s" lock within the specified time.', $key));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $sql = "DELETE FROM $this->table WHERE $this->idCol = :id AND $this->tokenCol = :token";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $stmt->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND $this->tokenCol = :token AND $this->expirationCol > {$this->getCurrentTimestampStatement()}";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Returns a hashed version of the key.
     */
    private function getHashedKey(Key $key): string
    {
        return hash('sha256', $key);
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }

    /**
     * @return \PDO|Connection
     */
    private function getConnection()
    {
        if (null === $this->conn) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->conn;
    }

    /**
     * Creates the table to store lock keys which can be called once for setup.
     *
     * @throws \PDOException    When the table already exists
     * @throws DBALException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable(): void
    {
        // connect if we are not yet
        $conn = $this->getConnection();
        $driver = $this->getDriver();

        if ($conn instanceof Connection) {
            $schema = new Schema();
            $table = $schema->createTable($this->table);
            $table->addColumn($this->idCol, 'string', array('length' => 64));
            $table->addColumn($this->tokenCol, 'string', array('length' => 44));
            $table->addColumn($this->expirationCol, 'integer', array('unsigned' => true));
            $table->setPrimaryKey(array($this->idCol));

            foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
                $conn->exec($sql);
            }

            return;
        }

        switch ($driver) {
            case 'mysql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(44) NOT NULL, $this->expirationCol INTEGER UNSIGNED NOT NULL) COLLATE utf8_bin, ENGINE = InnoDB";
                break;
            case 'sqlite':
                $sql = "CREATE TABLE $this->table ($this->idCol TEXT NOT NULL PRIMARY KEY, $this->tokenCol TEXT NOT NULL, $this->expirationCol INTEGER)";
                break;
            case 'pgsql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(64) NOT NULL, $this->expirationCol INTEGER)";
                break;
            case 'oci':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR2(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR2(64) NOT NULL, $this->expirationCol INTEGER)";
                break;
            case 'sqlsrv':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(64) NOT NULL, $this->expirationCol INTEGER)";
                break;
            default:
                throw new \DomainException(sprintf('Creating the lock table is currently not implemented for PDO driver "%s".', $driver));
        }

        $conn->exec($sql);
    }

    /**
     * Cleanups the table by removing all expired locks.
     */
    private function prune(): void
    {
        $sql = "DELETE FROM $this->table WHERE $this->expirationCol <= {$this->getCurrentTimestampStatement()}";

        $stmt = $this->getConnection()->prepare($sql);

        $stmt->execute();
    }

    private function getDriver(): string
    {
        if (null !== $this->driver) {
            return $this->driver;
        }

        $con = $this->getConnection();
        if ($con instanceof \PDO) {
            $this->driver = $con->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } else {
            switch ($this->driver = $con->getDriver()->getName()) {
                case 'mysqli':
                case 'pdo_mysql':
                case 'drizzle_pdo_mysql':
                    $this->driver = 'mysql';
                    break;
                case 'pdo_sqlite':
                    $this->driver = 'sqlite';
                    break;
                case 'pdo_pgsql':
                    $this->driver = 'pgsql';
                    break;
                case 'oci8':
                case 'pdo_oracle':
                    $this->driver = 'oci';
                    break;
                case 'pdo_sqlsrv':
                    $this->driver = 'sqlsrv';
                    break;
            }
        }

        return $this->driver;
    }

    /**
     * Provides a SQL function to get the current timestamp regarding the current connection's driver.
     */
    private function getCurrentTimestampStatement(): string
    {
        switch ($this->getDriver()) {
            case 'mysql':
                return 'UNIX_TIMESTAMP()';
            case 'sqlite':
                return 'strftime(\'%s\',\'now\')';
            case 'pgsql':
                return 'CAST(EXTRACT(epoch FROM NOW()) AS INT)';
            case 'oci':
                return '(SYSDATE - TO_DATE(\'19700101\',\'yyyymmdd\'))*86400 - TO_NUMBER(SUBSTR(TZ_OFFSET(sessiontimezone), 1, 3))*3600';
            case 'sqlsrv':
                return 'DATEDIFF(s, \'1970-01-01\', GETUTCDATE())';
            default:
                return time();
        }
    }
}
