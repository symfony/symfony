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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;

class PdoAdapter extends AbstractAdapter implements PruneableInterface
{
    protected $maxIdLength = 255;

    private $marshaller;
    private $conn;
    private $dsn;
    private $driver;
    private $serverVersion;
    private $table = 'cache_items';
    private $idCol = 'item_id';
    private $dataCol = 'item_data';
    private $lifetimeCol = 'item_lifetime';
    private $timeCol = 'item_time';
    private $username = '';
    private $password = '';
    private $connectionOptions = [];
    private $namespace;

    private $dbalAdapter;

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
     * @param \PDO|string $connOrDsn
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct($connOrDsn, string $namespace = '', int $defaultLifetime = 0, array $options = [], MarshallerInterface $marshaller = null)
    {
        if ($connOrDsn instanceof Connection || (\is_string($connOrDsn) && str_contains($connOrDsn, '://'))) {
            trigger_deprecation('symfony/cache', '5.4', 'Usage of a DBAL Connection with "%s" is deprecated and will be removed in symfony 6.0. Use "%s" instead.', __CLASS__, DoctrineDbalAdapter::class);
            $this->dbalAdapter = new DoctrineDbalAdapter($connOrDsn, $namespace, $defaultLifetime, $options, $marshaller);

            return;
        }

        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', __CLASS__));
            }

            $this->conn = $connOrDsn;
        } elseif (\is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, get_debug_type($connOrDsn)));
        }

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

    /**
     * {@inheritDoc}
     */
    public function getItem($key)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->getItem($key);
        }

        return parent::getItem($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(array $keys = [])
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->getItems($keys);
        }

        return parent::getItems($keys);
    }

    /**
     * {@inheritDoc}
     */
    public function hasItem($key)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->hasItem($key);
        }

        return parent::hasItem($key);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteItem($key)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->deleteItem($key);
        }

        return parent::deleteItem($key);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteItems(array $keys)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->deleteItems($keys);
        }

        return parent::deleteItems($keys);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $prefix = '')
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->clear($prefix);
        }

        return parent::clear($prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->get($key, $callback, $beta, $metadata);
        }

        return parent::get($key, $callback, $beta, $metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->delete($key);
        }

        return parent::delete($key);
    }

    /**
     * {@inheritDoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->save($item);
        }

        return parent::save($item);
    }

    /**
     * {@inheritDoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->saveDeferred($item);
        }

        return parent::saveDeferred($item);
    }

    /**
     * {@inheritDoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        if (isset($this->dbalAdapter)) {
            $this->dbalAdapter->setLogger($logger);

            return;
        }

        parent::setLogger($logger);
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->commit();
        }

        return parent::commit();
    }

    /**
     * {@inheritDoc}
     */
    public function reset()
    {
        if (isset($this->dbalAdapter)) {
            $this->dbalAdapter->reset();

            return;
        }

        parent::reset();
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
    public function createTable()
    {
        if (isset($this->dbalAdapter)) {
            $this->dbalAdapter->createTable();

            return;
        }

        // connect if we are not yet
        $conn = $this->getConnection();

        switch ($this->driver) {
            case 'mysql':
                // We use varbinary for the ID column because it prevents unwanted conversions:
                // - character set conversions between server and client
                // - trailing space removal
                // - case-insensitivity
                // - language processing like é == e
                $sql = "CREATE TABLE $this->table ($this->idCol VARBINARY(255) NOT NULL PRIMARY KEY, $this->dataCol MEDIUMBLOB NOT NULL, $this->lifetimeCol INTEGER UNSIGNED, $this->timeCol INTEGER UNSIGNED NOT NULL) COLLATE utf8mb4_bin, ENGINE = InnoDB";
                break;
            case 'sqlite':
                $sql = "CREATE TABLE $this->table ($this->idCol TEXT NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'pgsql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(255) NOT NULL PRIMARY KEY, $this->dataCol BYTEA NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'oci':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR2(255) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'sqlsrv':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(255) NOT NULL PRIMARY KEY, $this->dataCol VARBINARY(MAX) NOT NULL, $this->lifetimeCol INTEGER, $this->timeCol INTEGER NOT NULL)";
                break;
            default:
                throw new \DomainException(sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $this->driver));
        }

        $conn->exec($sql);
    }

    /**
     * Adds the Table to the Schema if the adapter uses this Connection.
     *
     * @deprecated since symfony/cache 5.4 use DoctrineDbalAdapter instead
     */
    public function configureSchema(Schema $schema, Connection $forConnection): void
    {
        if (isset($this->dbalAdapter)) {
            $this->dbalAdapter->configureSchema($schema, $forConnection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prune()
    {
        if (isset($this->dbalAdapter)) {
            return $this->dbalAdapter->prune();
        }

        $deleteSql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= :time";

        if ('' !== $this->namespace) {
            $deleteSql .= " AND $this->idCol LIKE :namespace";
        }

        $connection = $this->getConnection();

        try {
            $delete = $connection->prepare($deleteSql);
        } catch (\PDOException $e) {
            return true;
        }
        $delete->bindValue(':time', time(), \PDO::PARAM_INT);

        if ('' !== $this->namespace) {
            $delete->bindValue(':namespace', sprintf('%s%%', $this->namespace), \PDO::PARAM_STR);
        }
        try {
            return $delete->execute();
        } catch (\PDOException $e) {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
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

    /**
     * {@inheritdoc}
     */
    protected function doHave(string $id)
    {
        $connection = $this->getConnection();

        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND ($this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > :time)";
        $stmt = $connection->prepare($sql);

        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear(string $namespace)
    {
        $conn = $this->getConnection();

        if ('' === $namespace) {
            if ('sqlite' === $this->driver) {
                $sql = "DELETE FROM $this->table";
            } else {
                $sql = "TRUNCATE TABLE $this->table";
            }
        } else {
            $sql = "DELETE FROM $this->table WHERE $this->idCol LIKE '$namespace%'";
        }

        try {
            $conn->exec($sql);
        } catch (\PDOException $e) {
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = "DELETE FROM $this->table WHERE $this->idCol IN ($sql)";
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($ids));
        } catch (\PDOException $e) {
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, int $lifetime)
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $conn = $this->getConnection();

        $driver = $this->driver;
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
            if (!$conn->inTransaction() || \in_array($this->driver, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
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
                if (!$conn->inTransaction() || \in_array($this->driver, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                    $this->createTable();
                }
                $stmt->execute();
            }
            if (null === $driver && !$stmt->rowCount()) {
                try {
                    $insertStmt->execute();
                } catch (\PDOException $e) {
                    // A concurrent write won, let it be
                }
            }
        }

        return $failed;
    }

    /**
     * @internal
     */
    protected function getId($key)
    {
        if ('pgsql' !== $this->driver ?? ($this->getConnection() ? $this->driver : null)) {
            return parent::getId($key);
        }

        if (str_contains($key, "\0") || str_contains($key, '%') || !preg_match('//u', $key)) {
            $key = rawurlencode($key);
        }

        return parent::getId($key);
    }

    private function getConnection(): \PDO
    {
        if (null === $this->conn) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connectionOptions);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        if (null === $this->driver) {
            $this->driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }

        return $this->conn;
    }

    private function getServerVersion(): string
    {
        if (null === $this->serverVersion) {
            $this->serverVersion = $this->conn->getAttribute(\PDO::ATTR_SERVER_VERSION);
        }

        return $this->serverVersion;
    }
}
