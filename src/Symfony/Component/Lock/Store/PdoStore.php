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
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * PdoStore is a PersistingStoreInterface implementation using a PDO connection.
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
class PdoStore implements PersistingStoreInterface
{
    use DatabaseTableTrait;
    use ExpiringStoreTrait;

    private $conn;
    private $dsn;
    private $driver;
    private $username = '';
    private $password = '';
    private $connectionOptions = [];

    private $dbalStore;

    /**
     * You can either pass an existing database connection as PDO instance
     * or a DSN string that will be used to lazy-connect to the database
     * when the lock is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: lock_keys]
     *  * db_id_col: The column where to store the lock key [default: key_id]
     *  * db_token_col: The column where to store the lock token [default: key_token]
     *  * db_expiration_col: The column where to store the expiration [default: key_expiration]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @param \PDO|string $connOrDsn     A \PDO instance or DSN string or null
     * @param array       $options       An associative array of options
     * @param float       $gcProbability Probability expressed as floating number between 0 and 1 to clean old locks
     * @param int         $initialTtl    The expiration delay of locks in seconds
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When the initial ttl is not valid
     */
    public function __construct($connOrDsn, array $options = [], float $gcProbability = 0.01, int $initialTtl = 300)
    {
        if ($connOrDsn instanceof Connection || (\is_string($connOrDsn) && str_contains($connOrDsn, '://'))) {
            trigger_deprecation('symfony/lock', '5.4', 'Usage of a DBAL Connection with "%s" is deprecated and will be removed in symfony 6.0. Use "%s" instead.', __CLASS__, DoctrineDbalStore::class);
            $this->dbalStore = new DoctrineDbalStore($connOrDsn, $options, $gcProbability, $initialTtl);

            return;
        }

        $this->init($options, $gcProbability, $initialTtl);

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }

            $this->conn = $connOrDsn;
        } elseif (\is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, get_debug_type($connOrDsn)));
        }

        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connectionOptions = $options['db_connection_options'] ?? $this->connectionOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        if (isset($this->dbalStore)) {
            $this->dbalStore->save($key);

            return;
        }

        $key->reduceLifetime($this->initialTtl);

        $sql = "INSERT INTO $this->table ($this->idCol, $this->tokenCol, $this->expirationCol) VALUES (:id, :token, {$this->getCurrentTimestampStatement()} + $this->initialTtl)";
        $conn = $this->getConnection();
        try {
            $stmt = $conn->prepare($sql);
        } catch (\PDOException $e) {
            if (!$conn->inTransaction() || \in_array($this->driver, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                $this->createTable();
            }
            $stmt = $conn->prepare($sql);
        }

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        }

        $this->randomlyPrune();
        $this->checkNotExpired($key);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        if (isset($this->dbalStore)) {
            $this->dbalStore->putOffExpiration($key, $ttl);

            return;
        }

        if ($ttl < 1) {
            throw new InvalidTtlException(sprintf('"%s()" expects a TTL greater or equals to 1 second. Got "%s".', __METHOD__, $ttl));
        }

        $key->reduceLifetime($ttl);

        $sql = "UPDATE $this->table SET $this->expirationCol = {$this->getCurrentTimestampStatement()} + $ttl, $this->tokenCol = :token1 WHERE $this->idCol = :id AND ($this->tokenCol = :token2 OR $this->expirationCol <= {$this->getCurrentTimestampStatement()})";
        $stmt = $this->getConnection()->prepare($sql);

        $uniqueToken = $this->getUniqueToken($key);
        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token1', $uniqueToken);
        $stmt->bindValue(':token2', $uniqueToken);
        $result = $stmt->execute();

        // If this method is called twice in the same second, the row wouldn't be updated. We have to call exists to know if we are the owner
        if (!(\is_object($result) ? $result : $stmt)->rowCount() && !$this->exists($key)) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        if (isset($this->dbalStore)) {
            $this->dbalStore->delete($key);

            return;
        }

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
        if (isset($this->dbalStore)) {
            return $this->dbalStore->exists($key);
        }

        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND $this->tokenCol = :token AND $this->expirationCol > {$this->getCurrentTimestampStatement()}";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $result = $stmt->execute();

        return (bool) (\is_object($result) ? $result->fetchOne() : $stmt->fetchColumn());
    }

    private function getConnection(): \PDO
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
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable(): void
    {
        if (isset($this->dbalStore)) {
            $this->dbalStore->createTable();

            return;
        }

        // connect if we are not yet
        $conn = $this->getConnection();
        $driver = $this->getDriver();

        switch ($driver) {
            case 'mysql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(44) NOT NULL, $this->expirationCol INTEGER UNSIGNED NOT NULL) COLLATE utf8mb4_bin, ENGINE = InnoDB";
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
                throw new \DomainException(sprintf('Creating the lock table is currently not implemented for platform "%s".', $driver));
        }

        $conn->exec($sql);
    }

    /**
     * Adds the Table to the Schema if it doesn't exist.
     *
     * @deprecated since symfony/lock 5.4 use DoctrineDbalStore instead
     */
    public function configureSchema(Schema $schema): void
    {
        if (isset($this->dbalStore)) {
            $this->dbalStore->configureSchema($schema);

            return;
        }

        throw new \BadMethodCallException(sprintf('"%s::%s()" is only supported when using a doctrine/dbal Connection.', __CLASS__, __METHOD__));
    }

    /**
     * Cleans up the table by removing all expired locks.
     */
    private function prune(): void
    {
        $sql = "DELETE FROM $this->table WHERE $this->expirationCol <= {$this->getCurrentTimestampStatement()}";

        $this->getConnection()->exec($sql);
    }

    private function getDriver(): string
    {
        if (null !== $this->driver) {
            return $this->driver;
        }

        $conn = $this->getConnection();
        $this->driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return $this->driver;
    }

    /**
     * Provides an SQL function to get the current timestamp regarding the current connection's driver.
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
                return (string) time();
        }
    }
}
