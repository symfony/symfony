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
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class PdoAdapter extends AbstractAdapter
{
    protected $maxIdLength = 255;

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
    private $connectionOptions = array();

    /**
     * Constructor.
     *
     * You can either pass an existing database connection as PDO instance or
     * a Doctrine DBAL Connection or a DSN string that will be used to
     * lazy-connect to the database when the cache is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: array()]
     *
     * @param \PDO|Connection|string $connOrDsn       A \PDO or Connection instance or DSN string or null
     * @param string                 $namespace
     * @param int                    $defaultLifetime
     * @param array                  $options         An associative array of options
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct($connOrDsn, $namespace = '', $defaultLifetime = 0, array $options = array())
    {
        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }

        if ($connOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $connOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', __CLASS__));
            }

            $this->conn = $connOrDsn;
        } elseif ($connOrDsn instanceof Connection) {
            $this->conn = $connOrDsn;
        } elseif (is_string($connOrDsn)) {
            $this->dsn = $connOrDsn;
        } else {
            throw new InvalidArgumentException(sprintf('"%s" requires PDO or Doctrine\DBAL\Connection instance or DSN string as first argument, "%s" given.', __CLASS__, is_object($connOrDsn) ? get_class($connOrDsn) : gettype($connOrDsn)));
        }

        $this->table = isset($options['db_table']) ? $options['db_table'] : $this->table;
        $this->idCol = isset($options['db_id_col']) ? $options['db_id_col'] : $this->idCol;
        $this->dataCol = isset($options['db_data_col']) ? $options['db_data_col'] : $this->dataCol;
        $this->lifetimeCol = isset($options['db_lifetime_col']) ? $options['db_lifetime_col'] : $this->lifetimeCol;
        $this->timeCol = isset($options['db_time_col']) ? $options['db_time_col'] : $this->timeCol;
        $this->username = isset($options['db_username']) ? $options['db_username'] : $this->username;
        $this->password = isset($options['db_password']) ? $options['db_password'] : $this->password;
        $this->connectionOptions = isset($options['db_connection_options']) ? $options['db_connection_options'] : $this->connectionOptions;

        parent::__construct($namespace, $defaultLifetime);
    }

    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws \PDOException    When the table already exists
     * @throws DBALException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable()
    {
        // connect if we are not yet
        $conn = $this->getConnection();

        if ($conn instanceof Connection) {
            $schema = new Schema();
            $table = $schema->createTable($this->table);
            $table->addColumn($this->idCol, 'blob', array('length' => 255));
            $table->addColumn($this->dataCol, 'blob', array('length' => 16777215));
            $table->addColumn($this->lifetimeCol, 'integer', array('unsigned' => true, 'notnull' => false));
            $table->addColumn($this->timeCol, 'integer', array('unsigned' => true, 'foo' => 'bar'));
            $table->setPrimaryKey(array($this->idCol));

            foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
                $conn->exec($sql);
            }

            return;
        }

        switch ($this->driver) {
            case 'mysql':
                // We use varbinary for the ID column because it prevents unwanted conversions:
                // - character set conversions between server and client
                // - trailing space removal
                // - case-insensitivity
                // - language processing like é == e
                $sql = "CREATE TABLE $this->table ($this->idCol VARBINARY(255) NOT NULL PRIMARY KEY, $this->dataCol MEDIUMBLOB NOT NULL, $this->lifetimeCol INTEGER UNSIGNED, $this->timeCol INTEGER UNSIGNED NOT NULL) COLLATE utf8_bin, ENGINE = InnoDB";
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
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $now = time();
        $expired = array();

        $sql = str_pad('', (count($ids) << 1) - 1, '?,');
        $sql = "SELECT $this->idCol, CASE WHEN $this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > ? THEN $this->dataCol ELSE NULL END FROM $this->table WHERE $this->idCol IN ($sql)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
        foreach ($ids as $id) {
            $stmt->bindValue(++$i, $id);
        }
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => parent::unserialize(is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }

        if ($expired) {
            $sql = str_pad('', (count($expired) << 1) - 1, '?,');
            $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol <= ? AND $this->idCol IN ($sql)";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->bindValue($i = 1, $now, \PDO::PARAM_INT);
            foreach ($expired as $id) {
                $stmt->bindValue(++$i, $id);
            }
            $stmt->execute($expired);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        $sql = "SELECT 1 FROM $this->table WHERE $this->idCol = :id AND ($this->lifetimeCol IS NULL OR $this->lifetimeCol + $this->timeCol > :time)";
        $stmt = $this->getConnection()->prepare($sql);

        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
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

        $conn->exec($sql);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $sql = str_pad('', (count($ids) << 1) - 1, '?,');
        $sql = "DELETE FROM $this->table WHERE $this->idCol IN ($sql)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($ids));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $serialized = array();
        $failed = array();

        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        if (!$serialized) {
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
        $stmt = $conn->prepare($sql);

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

        foreach ($serialized as $id => $data) {
            $stmt->execute();

            if (null === $driver && !$stmt->rowCount()) {
                try {
                    $insertStmt->execute();
                } catch (DBALException $e) {
                } catch (\PDOException $e) {
                    // A concurrent write won, let it be
                }
            }
        }

        return $failed;
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
        if (null === $this->driver) {
            if ($this->conn instanceof \PDO) {
                $this->driver = $this->conn->getAttribute(\PDO::ATTR_DRIVER_NAME);
            } else {
                switch ($this->driver = $this->conn->getDriver()->getName()) {
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
        }

        return $this->conn;
    }

    /**
     * @return string
     */
    private function getServerVersion()
    {
        if (null === $this->serverVersion) {
            $conn = $this->conn instanceof \PDO ? $this->conn : $this->conn->getWrappedConnection();
            if ($conn instanceof \PDO) {
                $this->serverVersion = $conn->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } elseif ($conn instanceof ServerInfoAwareConnection) {
                $this->serverVersion = $conn->getServerVersion();
            } else {
                $this->serverVersion = '0';
            }
        }

        return $this->serverVersion;
    }
}
