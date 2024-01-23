<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;

class DoctrineDbalAdapter extends AbstractAdapter implements PruneableInterface
{
    private const MAX_KEY_LENGTH = 255;

    private MarshallerInterface $marshaller;
    private Connection $conn;
    private string $platformName;
    private string $table = 'cache_items';
    private string $idCol = 'item_id';
    private string $dataCol = 'item_data';
    private string $lifetimeCol = 'item_lifetime';
    private string $timeCol = 'item_time';

    /**
     * You can either pass an existing database Doctrine DBAL Connection or
     * a DSN string that will be used to connect to the database.
     *
     * The cache table is created automatically when possible.
     * Otherwise, use the createTable() method.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(
        Connection|string $connOrDsn,
        private string $namespace = '',
        int $defaultLifetime = 0,
        array $options = [],
        ?MarshallerInterface $marshaller = null,
    ) {
        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }

        if ($connOrDsn instanceof Connection) {
            $this->conn = $connOrDsn;
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
            ]))->parse($connOrDsn);

            $config = new Configuration();
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

            $this->conn = DriverManager::getConnection($params, $config);
        }

        $this->maxIdLength = self::MAX_KEY_LENGTH;
        $this->table = $options['db_table'] ?? $this->table;
        $this->idCol = $options['db_id_col'] ?? $this->idCol;
        $this->dataCol = $options['db_data_col'] ?? $this->dataCol;
        $this->lifetimeCol = $options['db_lifetime_col'] ?? $this->lifetimeCol;
        $this->timeCol = $options['db_time_col'] ?? $this->timeCol;
        $this->marshaller = $marshaller ?? new DefaultMarshaller();

        parent::__construct($namespace, $defaultLifetime);
    }

    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws DBALException When the table already exists
     */
    public function createTable(): void
    {
        $schema = new Schema();
        $this->addTableToSchema($schema);

        foreach ($schema->toSql($this->conn->getDatabasePlatform()) as $sql) {
            $this->conn->executeStatement($sql);
        }
    }

    public function configureSchema(Schema $schema, Connection $forConnection, \Closure $isSameDatabase): void
    {
        if ($schema->hasTable($this->table)) {
            return;
        }

        if ($forConnection !== $this->conn && !$isSameDatabase($this->conn->executeStatement(...))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    public function prune(): bool
    {
        $deleteSql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= ?";
        $params = [time()];
        $paramTypes = [ParameterType::INTEGER];

        if ('' !== $this->namespace) {
            $deleteSql .= " AND $this->idCol LIKE ?";
            $params[] = sprintf('%s%%', $this->namespace);
            $paramTypes[] = ParameterType::STRING;
        }

        try {
            $this->conn->executeStatement($deleteSql, $params, $paramTypes);
        } catch (TableNotFoundException) {
        }

        return true;
    }

    protected function doFetch(array $ids): iterable
    {
        $now = time();
        $expired = [];

        $sql = "SELECT $this->idCol, CASE WHEN $this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > ? THEN $this->dataCol ELSE NULL END FROM $this->table WHERE $this->idCol IN (?)";
        $result = $this->conn->executeQuery($sql, [
            $now,
            $ids,
        ], [
            ParameterType::INTEGER,
            ArrayParameterType::STRING,
        ])->iterateNumeric();

        foreach ($result as $row) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => $this->marshaller->unmarshall(\is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }

        if ($expired) {
            $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= ? AND $this->idCol IN (?)";
            $this->conn->executeStatement($sql, [
                $now,
                $expired,
            ], [
                ParameterType::INTEGER,
                ArrayParameterType::STRING,
            ]);
        }
    }

    protected function doHave(string $id): bool
    {
        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = ? AND ($this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > ?)";
        $result = $this->conn->executeQuery($sql, [
            $id,
            time(),
        ], [
            ParameterType::STRING,
            ParameterType::INTEGER,
        ]);

        return (bool) $result->fetchOne();
    }

    protected function doClear(string $namespace): bool
    {
        if ('' === $namespace) {
            $sql = $this->conn->getDatabasePlatform()->getTruncateTableSQL($this->table);
        } else {
            $sql = "DELETE FROM $this->table WHERE $this->idCol LIKE '$namespace%'";
        }

        try {
            $this->conn->executeStatement($sql);
        } catch (TableNotFoundException) {
        }

        return true;
    }

    protected function doDelete(array $ids): bool
    {
        $sql = "DELETE FROM $this->table WHERE $this->idCol IN (?)";
        try {
            $this->conn->executeStatement($sql, [array_values($ids)], [ArrayParameterType::STRING]);
        } catch (TableNotFoundException) {
        }

        return true;
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $platformName = $this->getPlatformName();
        $insertSql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?)";

        switch ($platformName) {
            case 'mysql':
                $sql = $insertSql." ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->lifetimeCol = VALUES($this->lifetimeCol), $this->timeCol = VALUES($this->timeCol)";
                break;
            case 'oci':
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO $this->table USING DUAL ON ($this->idCol = ?) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ?";
                break;
            case 'sqlsrv':
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = ?) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ?;";
                break;
            case 'sqlite':
                $sql = 'INSERT OR REPLACE'.substr($insertSql, 6);
                break;
            case 'pgsql':
                $sql = $insertSql." ON CONFLICT ($this->idCol) DO UPDATE SET ($this->dataCol, $this->lifetimeCol, $this->timeCol) = (EXCLUDED.$this->dataCol, EXCLUDED.$this->lifetimeCol, EXCLUDED.$this->timeCol)";
                break;
            default:
                $platformName = null;
                $sql = "UPDATE $this->table SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ? WHERE $this->idCol = ?";
                break;
        }

        $now = time();
        $lifetime = $lifetime ?: null;
        try {
            $stmt = $this->conn->prepare($sql);
        } catch (TableNotFoundException) {
            if (!$this->conn->isTransactionActive() || \in_array($platformName, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                $this->createTable();
            }
            $stmt = $this->conn->prepare($sql);
        }

        if ('sqlsrv' === $platformName || 'oci' === $platformName) {
            $bind = static function ($id, $data) use ($stmt) {
                $stmt->bindValue(1, $id);
                $stmt->bindValue(2, $id);
                $stmt->bindValue(3, $data, ParameterType::LARGE_OBJECT);
                $stmt->bindValue(6, $data, ParameterType::LARGE_OBJECT);
            };
            $stmt->bindValue(4, $lifetime, ParameterType::INTEGER);
            $stmt->bindValue(5, $now, ParameterType::INTEGER);
            $stmt->bindValue(7, $lifetime, ParameterType::INTEGER);
            $stmt->bindValue(8, $now, ParameterType::INTEGER);
        } elseif (null !== $platformName) {
            $bind = static function ($id, $data) use ($stmt) {
                $stmt->bindValue(1, $id);
                $stmt->bindValue(2, $data, ParameterType::LARGE_OBJECT);
            };
            $stmt->bindValue(3, $lifetime, ParameterType::INTEGER);
            $stmt->bindValue(4, $now, ParameterType::INTEGER);
        } else {
            $stmt->bindValue(2, $lifetime, ParameterType::INTEGER);
            $stmt->bindValue(3, $now, ParameterType::INTEGER);

            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bindValue(3, $lifetime, ParameterType::INTEGER);
            $insertStmt->bindValue(4, $now, ParameterType::INTEGER);

            $bind = static function ($id, $data) use ($stmt, $insertStmt) {
                $stmt->bindValue(1, $data, ParameterType::LARGE_OBJECT);
                $stmt->bindValue(4, $id);
                $insertStmt->bindValue(1, $id);
                $insertStmt->bindValue(2, $data, ParameterType::LARGE_OBJECT);
            };
        }

        foreach ($values as $id => $data) {
            $bind($id, $data);
            try {
                $rowCount = $stmt->executeStatement();
            } catch (TableNotFoundException) {
                if (!$this->conn->isTransactionActive() || \in_array($platformName, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                    $this->createTable();
                }
                $rowCount = $stmt->executeStatement();
            }
            if (null === $platformName && 0 === $rowCount) {
                try {
                    $insertStmt->executeStatement();
                } catch (DBALException) {
                    // A concurrent write won, let it be
                }
            }
        }

        return $failed;
    }

    /**
     * @internal
     */
    protected function getId(mixed $key): string
    {
        if ('pgsql' !== $this->platformName ??= $this->getPlatformName()) {
            return parent::getId($key);
        }

        if (str_contains($key, "\0") || str_contains($key, '%') || !preg_match('//u', $key)) {
            $key = rawurlencode($key);
        }

        return parent::getId($key);
    }

    private function getPlatformName(): string
    {
        if (isset($this->platformName)) {
            return $this->platformName;
        }

        $platform = $this->conn->getDatabasePlatform();

        return $this->platformName = match (true) {
            $platform instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform => 'mysql',
            $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform => 'sqlite',
            $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform => 'pgsql',
            $platform instanceof \Doctrine\DBAL\Platforms\OraclePlatform => 'oci',
            $platform instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform => 'sqlsrv',
            default => $platform::class,
        };
    }

    private function addTableToSchema(Schema $schema): void
    {
        $types = [
            'mysql' => 'binary',
            'sqlite' => 'text',
        ];

        $table = $schema->createTable($this->table);
        $table->addColumn($this->idCol, $types[$this->getPlatformName()] ?? 'string', ['length' => 255]);
        $table->addColumn($this->dataCol, 'blob', ['length' => 16777215]);
        $table->addColumn($this->lifetimeCol, 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn($this->timeCol, 'integer', ['unsigned' => true]);
        $table->setPrimaryKey([$this->idCol]);
    }
}
