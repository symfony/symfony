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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * DbalStore is a PersistingStoreInterface implementation using a Doctrine DBAL connection.
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
class DoctrineDbalStore implements PersistingStoreInterface
{
    use DatabaseTableTrait;
    use ExpiringStoreTrait;

    private $conn;

    /**
     * List of available options:
     *  * db_table: The name of the table [default: lock_keys]
     *  * db_id_col: The column where to store the lock key [default: key_id]
     *  * db_token_col: The column where to store the lock token [default: key_token]
     *  * db_expiration_col: The column where to store the expiration [default: key_expiration].
     *
     * @param Connection|string $connOrUrl     A DBAL Connection instance or Doctrine URL
     * @param array             $options       An associative array of options
     * @param float             $gcProbability Probability expressed as floating number between 0 and 1 to clean old locks
     * @param int               $initialTtl    The expiration delay of locks in seconds
     *
     * @throws InvalidArgumentException When namespace contains invalid characters
     * @throws InvalidArgumentException When the initial ttl is not valid
     */
    public function __construct($connOrUrl, array $options = [], float $gcProbability = 0.01, int $initialTtl = 300)
    {
        $this->init($options, $gcProbability, $initialTtl);

        if ($connOrUrl instanceof Connection) {
            $this->conn = $connOrUrl;
        } elseif (\is_string($connOrUrl)) {
            if (!class_exists(DriverManager::class)) {
                throw new InvalidArgumentException(sprintf('Failed to parse the DSN "%s". Try running "composer require doctrine/dbal".', $connOrUrl));
            }
            $this->conn = DriverManager::getConnection(['url' => $connOrUrl]);
        } else {
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be "%s" or string, "%s" given.', Connection::class, __METHOD__, get_debug_type($connOrUrl)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $key->reduceLifetime($this->initialTtl);

        $sql = "INSERT INTO $this->table ($this->idCol, $this->tokenCol, $this->expirationCol) VALUES (?, ?, {$this->getCurrentTimestampStatement()} + $this->initialTtl)";

        try {
            $this->conn->executeStatement($sql, [
                $this->getHashedKey($key),
                $this->getUniqueToken($key),
            ], [
                ParameterType::STRING,
                ParameterType::STRING,
            ]);
        } catch (TableNotFoundException $e) {
            if (!$this->conn->isTransactionActive() || $this->platformSupportsTableCreationInTransaction()) {
                $this->createTable();
            }

            try {
                $this->conn->executeStatement($sql, [
                    $this->getHashedKey($key),
                    $this->getUniqueToken($key),
                ], [
                    ParameterType::STRING,
                    ParameterType::STRING,
                ]);
            } catch (DBALException $e) {
                $this->putOffExpiration($key, $this->initialTtl);
            }
        } catch (DBALException $e) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        }

        $this->randomlyPrune();
        $this->checkNotExpired($key);
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        if ($ttl < 1) {
            throw new InvalidTtlException(sprintf('"%s()" expects a TTL greater or equals to 1 second. Got "%s".', __METHOD__, $ttl));
        }

        $key->reduceLifetime($ttl);

        $sql = "UPDATE $this->table SET $this->expirationCol = {$this->getCurrentTimestampStatement()} + ?, $this->tokenCol = ? WHERE $this->idCol = ? AND ($this->tokenCol = ? OR $this->expirationCol <= {$this->getCurrentTimestampStatement()})";
        $uniqueToken = $this->getUniqueToken($key);

        $result = $this->conn->executeQuery($sql, [
            $ttl,
            $uniqueToken,
            $this->getHashedKey($key),
            $uniqueToken,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::STRING,
        ]);

        // If this method is called twice in the same second, the row wouldn't be updated. We have to call exists to know if we are the owner
        if (!$result->rowCount() && !$this->exists($key)) {
            throw new LockConflictedException();
        }

        $this->checkNotExpired($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $this->conn->delete($this->table, [
            $this->idCol => $this->getHashedKey($key),
            $this->tokenCol => $this->getUniqueToken($key),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = ? AND $this->tokenCol = ? AND $this->expirationCol > {$this->getCurrentTimestampStatement()}";
        $result = $this->conn->fetchOne($sql, [
            $this->getHashedKey($key),
            $this->getUniqueToken($key),
        ], [
            ParameterType::STRING,
            ParameterType::STRING,
        ]);

        return (bool) $result;
    }

    /**
     * Creates the table to store lock keys which can be called once for setup.
     *
     * @throws DBALException When the table already exists
     */
    public function createTable(): void
    {
        $schema = new Schema();
        $this->configureSchema($schema);

        foreach ($schema->toSql($this->conn->getDatabasePlatform()) as $sql) {
            $this->conn->executeStatement($sql);
        }
    }

    /**
     * Adds the Table to the Schema if it doesn't exist.
     */
    public function configureSchema(Schema $schema): void
    {
        if ($schema->hasTable($this->table)) {
            return;
        }

        $table = $schema->createTable($this->table);
        $table->addColumn($this->idCol, 'string', ['length' => 64]);
        $table->addColumn($this->tokenCol, 'string', ['length' => 44]);
        $table->addColumn($this->expirationCol, 'integer', ['unsigned' => true]);
        $table->setPrimaryKey([$this->idCol]);
    }

    /**
     * Cleans up the table by removing all expired locks.
     */
    private function prune(): void
    {
        $sql = "DELETE FROM $this->table WHERE $this->expirationCol <= {$this->getCurrentTimestampStatement()}";

        $this->conn->executeStatement($sql);
    }

    /**
     * Provides an SQL function to get the current timestamp regarding the current connection's driver.
     */
    private function getCurrentTimestampStatement(): string
    {
        $platform = $this->conn->getDatabasePlatform();
        switch (true) {
            case $platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\MySQL57Platform:
                return 'UNIX_TIMESTAMP()';

            case $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform:
                return 'strftime(\'%s\',\'now\')';

            case $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQL94Platform:
                return 'CAST(EXTRACT(epoch FROM NOW()) AS INT)';

            case $platform instanceof \Doctrine\DBAL\Platforms\OraclePlatform:
                return '(SYSDATE - TO_DATE(\'19700101\',\'yyyymmdd\'))*86400 - TO_NUMBER(SUBSTR(TZ_OFFSET(sessiontimezone), 1, 3))*3600';

            case $platform instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\SQLServer2012Platform:
                return 'DATEDIFF(s, \'1970-01-01\', GETUTCDATE())';

            default:
                return (string) time();
        }
    }

    /**
     * Checks wether current platform supports table creation within transaction.
     */
    private function platformSupportsTableCreationInTransaction(): bool
    {
        $platform = $this->conn->getDatabasePlatform();

        switch (true) {
            case $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQL94Platform:
            case $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform:
            case $platform instanceof \Doctrine\DBAL\Platforms\SQLServer2012Platform:
                return true;
            default:
                return false;
        }
    }
}
