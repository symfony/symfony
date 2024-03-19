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
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * PdoStore is a PersistingStoreInterface implementation using a PDO connection.
 *
 * Lock metadata are stored in a table. You can use createTable() to initialize
 * a correctly defined table.
 *
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

    private \PDO $conn;
    private string $dsn;
    private string $driver;
    private ?string $username = null;
    private ?string $password = null;
    private array $connectionOptions = [];

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
     * @param array $options       An associative array of options
     * @param float $gcProbability Probability expressed as floating number between 0 and 1 to clean old locks
     * @param int   $initialTtl    The expiration delay of locks in seconds
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When the initial ttl is not valid
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, #[\SensitiveParameter] array $options = [], float $gcProbability = 0.01, int $initialTtl = 300)
    {
        $this->init($options, $gcProbability, $initialTtl);

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __METHOD__));
            }

            $this->conn = $connOrDsn;
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
        $key->reduceLifetime($this->initialTtl);

        $sql = "INSERT INTO $this->table ($this->idCol, $this->tokenCol, $this->expirationCol) VALUES (:id, :token, {$this->getCurrentTimestampStatement()} + $this->initialTtl)";
        $conn = $this->getConnection();
        try {
            $stmt = $conn->prepare($sql);
        } catch (\PDOException $e) {
            if ($this->isTableMissing($e) && (!$conn->inTransaction() || \in_array($this->getDriver(), ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                $this->createTable();
            }
            $stmt = $conn->prepare($sql);
        }

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            if ($this->isTableMissing($e) && (!$conn->inTransaction() || \in_array($this->getDriver(), ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                $this->createTable();

                try {
                    $stmt->execute();
                } catch (\PDOException) {
                    $this->putOffExpiration($key, $this->initialTtl);
                }
            } else {
                // the lock is already acquired. It could be us. Let's try to put off.
                $this->putOffExpiration($key, $this->initialTtl);
            }
        }

        $this->randomlyPrune();
        $this->checkNotExpired($key);
    }

    /**
     * @return void
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
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
     * @return void
     */
    public function delete(Key $key)
    {
        $sql = "DELETE FROM $this->table WHERE $this->idCol = :id AND $this->tokenCol = :token";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $stmt->execute();
    }

    public function exists(Key $key): bool
    {
        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND $this->tokenCol = :token AND $this->expirationCol > {$this->getCurrentTimestampStatement()}";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $this->getHashedKey($key));
        $stmt->bindValue(':token', $this->getUniqueToken($key));
        $result = $stmt->execute();

        return (bool) (\is_object($result) ? $result->fetchOne() : $stmt->fetchColumn());
    }

    private function getConnection(): \PDO
    {
        if (!isset($this->conn)) {
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
        $sql = match ($driver = $this->getDriver()) {
            'mysql' => "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(44) NOT NULL, $this->expirationCol INTEGER UNSIGNED NOT NULL) COLLATE utf8mb4_bin, ENGINE = InnoDB",
            'sqlite' => "CREATE TABLE $this->table ($this->idCol TEXT NOT NULL PRIMARY KEY, $this->tokenCol TEXT NOT NULL, $this->expirationCol INTEGER)",
            'pgsql' => "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(64) NOT NULL, $this->expirationCol INTEGER)",
            'oci' => "CREATE TABLE $this->table ($this->idCol VARCHAR2(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR2(64) NOT NULL, $this->expirationCol INTEGER)",
            'sqlsrv' => "CREATE TABLE $this->table ($this->idCol VARCHAR(64) NOT NULL PRIMARY KEY, $this->tokenCol VARCHAR(64) NOT NULL, $this->expirationCol INTEGER)",
            default => throw new \DomainException(sprintf('Creating the lock table is currently not implemented for platform "%s".', $driver)),
        };

        $this->getConnection()->exec($sql);
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
        return $this->driver ??= $this->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Provides an SQL function to get the current timestamp regarding the current connection's driver.
     */
    private function getCurrentTimestampStatement(): string
    {
        return match ($this->getDriver()) {
            'mysql' => 'UNIX_TIMESTAMP()',
            'sqlite' => 'strftime(\'%s\',\'now\')',
            'pgsql' => 'CAST(EXTRACT(epoch FROM NOW()) AS INT)',
            'oci' => '(SYSDATE - TO_DATE(\'19700101\',\'yyyymmdd\'))*86400 - TO_NUMBER(SUBSTR(TZ_OFFSET(sessiontimezone), 1, 3))*3600',
            'sqlsrv' => 'DATEDIFF(s, \'1970-01-01\', GETUTCDATE())',
            default => (string) time(),
        };
    }

    private function isTableMissing(\PDOException $exception): bool
    {
        $driver = $this->getDriver();
        [$sqlState, $code] = $exception->errorInfo ?? [null, $exception->getCode()];

        return match ($driver) {
            'pgsql' => '42P01' === $sqlState,
            'sqlite' => str_contains($exception->getMessage(), 'no such table:'),
            'oci' => 942 === $code,
            'sqlsrv' => 208 === $code,
            'mysql' => 1146 === $code,
            default => false,
        };
    }
}
