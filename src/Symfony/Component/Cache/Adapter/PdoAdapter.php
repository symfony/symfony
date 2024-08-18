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

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;

class PdoAdapter extends AbstractAdapter implements PruneableInterface
{
    private const MAX_KEY_LENGTH = 255;

    private MarshallerInterface $marshaller;
    private \PDO $conn;
    private string $dsn;
    private string $driver;
    private string $serverVersion;
    private string $table = 'cache_items';
    private string $idCol = 'item_id';
    private string $dataCol = 'item_data';
    private string $lifetimeCol = 'item_lifetime';
    private string $timeCol = 'item_time';
    private ?string $username = null;
    private ?string $password = null;
    private array $connectionOptions = [];
    private string $namespace;

    /**
     * You can either pass an existing database connection as PDO instance or
     * a DSN string that will be used to lazy-connect to the database when the
     * cache is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(#[\SensitiveParameter] \PDO|string $connOrDsn, string $namespace = '', int $defaultLifetime = 0, array $options = [], ?MarshallerInterface $marshaller = null)
    {
        if (\is_string($connOrDsn) && str_contains($connOrDsn, '://')) {
            throw new InvalidArgumentException(\sprintf('Usage of Doctrine DBAL URL with "%s" is not supported. Use a PDO DSN or "%s" instead.', __CLASS__, DoctrineDbalAdapter::class));
        }

        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(\sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(\sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __CLASS__));
            }

            $this->conn = $connOrDsn;
        } else {
            $this->dsn = $connOrDsn;
        }

        $this->maxIdLength = self::MAX_KEY_LENGTH;
        $this->table = $options['db_table'] ?? $this->table;
        $this->idCol = $options['db_id_col'] ?? $this->idCol;
        $this->dataCol = $options['db_data_col'] ?? $this->dataCol;
        $this->lifetimeCol = $options['db_lifetime_col'] ?? $this->lifetimeCol;
        $this->timeCol = $options['db_time_col'] ?? $this->timeCol;
        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connectionOptions = $options['db_connection_options'] ?? $this->connectionOptions;
        $this->namespace = $namespace;
        $this->marshaller = $marshaller ?? new DefaultMarshaller();

        parent::__construct($namespace, $defaultLifetime);
    }

    public static function createConnection(#[\SensitiveParameter] string $dsn, array $options = []): \PDO|string
    {
        if ($options['lazy'] ?? true) {
            return $dsn;
        }

        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable(): void
    {
        $sql = match ($driver = $this->getDriver()) {
            // We use varbinary for the ID column because it prevents unwanted conversions:
            // - character set conversions between server and client
            // - trailing space removal
            // - case-insensitivity
            // - language processing like é == e
            'mysql' => "CREATE TABLE $this->table ($this->idCol VARBINARY(255) NOT NULL PRIMARY KEY, $this->dataCol MEDIUMBLOB NOT NULL, $this->lifetimeCol INTEGER UNSIGNED, $this->timeCol INTEGER UNSIGNED NOT NULL) COLLATE utf8mb4_bin, ENGINE = InnoDB",
            'sqlite' => "CREATE TABLE $this->table ($this->idCol TEXT NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)",
            'pgsql' => "CREATE TABLE $this->table ($this->idCol VARCHAR(255) NOT NULL PRIMARY KEY, $this->dataCol BYTEA NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)",
            'oci' => "CREATE TABLE $this->table ($this->idCol VARCHAR2(255) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)",
            'sqlsrv' => "CREATE TABLE $this->table ($this->idCol VARCHAR(255) NOT NULL PRIMARY KEY, $this->dataCol VARBINARY(MAX) NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)",
            default => throw new \DomainException(\sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $driver)),
        };

        $this->getConnection()->exec($sql);
    }

    public function prune(): bool
    {
        $deleteSql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= :time";

        if ('' !== $this->namespace) {
            $deleteSql .= " AND $this->idCol LIKE :namespace";
        }

        $connection = $this->getConnection();

        try {
            $delete = $connection->prepare($deleteSql);
        } catch (\PDOException) {
            return true;
        }
        $delete->bindValue(':time', time(), \PDO::PARAM_INT);

        if ('' !== $this->namespace) {
            $delete->bindValue(':namespace', \sprintf('%s%%', $this->namespace), \PDO::PARAM_STR);
        }
        try {
            return $delete->execute();
        } catch (\PDOException) {
            return true;
        }
    }

    protected function doFetch(array $ids): iterable
    {
        $connection = $this->getConnection();

        $now = time();
        $expired = [];

        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = "SELECT $this->idCol, CASE WHEN $this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > ? THEN $this->dataCol ELSE NULL END FROM $this->table WHERE $this->idCol IN ($sql)";
        $stmt = $connection->prepare($sql);
        $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
        foreach ($ids as $id) {
            $stmt->bindValue(++$i, $id);
        }
        $result = $stmt->execute();

        if (\is_object($result)) {
            $result = $result->iterateNumeric();
        } else {
            $stmt->setFetchMode(\PDO::FETCH_NUM);
            $result = $stmt;
        }

        foreach ($result as $row) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => $this->marshaller->unmarshall(\is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }

        if ($expired) {
            $sql = str_pad('', (\count($expired) << 1) - 1, '?,');
            $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= ? AND $this->idCol IN ($sql)";
            $stmt = $connection->prepare($sql);
            $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
            foreach ($expired as $id) {
                $stmt->bindValue(++$i, $id);
            }
            $stmt->execute();
        }
    }

    protected function doHave(string $id): bool
    {
        $connection = $this->getConnection();

        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND ($this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > :time)";
        $stmt = $connection->prepare($sql);

        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    protected function doClear(string $namespace): bool
    {
        $conn = $this->getConnection();

        if ('' === $namespace) {
            if ('sqlite' === $this->getDriver()) {
                $sql = "DELETE FROM $this->table";
            } else {
                $sql = "TRUNCATE TABLE $this->table";
            }
        } else {
            $sql = "DELETE FROM $this->table WHERE $this->idCol LIKE '$namespace%'";
        }

        try {
            $conn->exec($sql);
        } catch (\PDOException) {
        }

        return true;
    }

    protected function doDelete(array $ids): bool
    {
        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = "DELETE FROM $this->table WHERE $this->idCol IN ($sql)";
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($ids));
        } catch (\PDOException) {
        }

        return true;
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $conn = $this->getConnection();

        $driver = $this->getDriver();
        $insertSql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time)";

        switch (true) {
            case 'mysql' === $driver:
                $sql = $insertSql." ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->lifetimeCol = VALUES($this->lifetimeCol), $this->timeCol = VALUES($this->timeCol)";
                break;
            case 'oci' === $driver:
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO $this->table USING DUAL ON ($this->idCol = ?) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ?";
                break;
            case 'sqlsrv' === $driver && version_compare($this->getServerVersion(), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = ?) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ?;";
                break;
            case 'sqlite' === $driver:
                $sql = 'INSERT OR REPLACE'.substr($insertSql, 6);
                break;
            case 'pgsql' === $driver && version_compare($this->getServerVersion(), '9.5', '>='):
                $sql = $insertSql." ON CONFLICT ($this->idCol) DO UPDATE SET ($this->dataCol, $this->lifetimeCol, $this->timeCol) = (EXCLUDED.$this->dataCol, EXCLUDED.$this->lifetimeCol, EXCLUDED.$this->timeCol)";
                break;
            default:
                $driver = null;
                $sql = "UPDATE $this->table SET $this->dataCol = :data, $this->lifetimeCol = :lifetime, $this->timeCol = :time WHERE $this->idCol = :id";
                break;
        }

        $now = time();
        $lifetime = $lifetime ?: null;
        try {
            $stmt = $conn->prepare($sql);
        } catch (\PDOException $e) {
            if ($this->isTableMissing($e) && (!$conn->inTransaction() || \in_array($driver, ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                $this->createTable();
            }
            $stmt = $conn->prepare($sql);
        }

        // $id and $data are defined later in the loop. Binding is done by reference, values are read on execution.
        if ('sqlsrv' === $driver || 'oci' === $driver) {
            $stmt->bindParam(1, $id);
            $stmt->bindParam(2, $id);
            $stmt->bindParam(3, $data, \PDO::PARAM_LOB);
            $stmt->bindValue(4, $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(5, $now, \PDO::PARAM_INT);
            $stmt->bindParam(6, $data, \PDO::PARAM_LOB);
            $stmt->bindValue(7, $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(8, $now, \PDO::PARAM_INT);
        } else {
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $stmt->bindValue(':lifetime', $lifetime, \PDO::PARAM_INT);
            $stmt->bindValue(':time', $now, \PDO::PARAM_INT);
        }
        if (null === $driver) {
            $insertStmt = $conn->prepare($insertSql);

            $insertStmt->bindParam(':id', $id);
            $insertStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $insertStmt->bindValue(':lifetime', $lifetime, \PDO::PARAM_INT);
            $insertStmt->bindValue(':time', $now, \PDO::PARAM_INT);
        }

        foreach ($values as $id => $data) {
            try {
                $stmt->execute();
            } catch (\PDOException $e) {
                if ($this->isTableMissing($e) && (!$conn->inTransaction() || \in_array($driver, ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                    $this->createTable();
                }
                $stmt->execute();
            }
            if (null === $driver && !$stmt->rowCount()) {
                try {
                    $insertStmt->execute();
                } catch (\PDOException) {
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
        if ('pgsql' !== $this->getDriver()) {
            return parent::getId($key);
        }

        if (str_contains($key, "\0") || str_contains($key, '%') || !preg_match('//u', $key)) {
            $key = rawurlencode($key);
        }

        return parent::getId($key);
    }

    private function getConnection(): \PDO
    {
        if (!isset($this->conn)) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->conn;
    }

    private function getDriver(): string
    {
        return $this->driver ??= $this->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    private function getServerVersion(): string
    {
        return $this->serverVersion ??= $this->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);
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
