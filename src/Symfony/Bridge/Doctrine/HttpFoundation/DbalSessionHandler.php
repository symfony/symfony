<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\HttpFoundation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

/**
 * DBAL based session storage.
 *
 * This implementation is very similar to Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
 * but uses the Doctrine driver connection interface and thus also works with non-PDO-based drivers like mysqli and OCI8.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class DbalSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var DriverConnection
     */
    private $con;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string Column for session id
     */
    private $idCol = 'sess_id';

    /**
     * @var string Column for session data
     */
    private $dataCol = 'sess_data';

    /**
     * @var string Column for timestamp
     */
    private $timeCol = 'sess_time';

    /**
     * Constructor.
     *
     * @param DriverConnection $con       A driver connection, preferably a wrapper Doctrine\DBAL\Connection for lazy connections
     * @param string           $tableName Table name
     */
    public function __construct(DriverConnection $con, $tableName = 'sessions')
    {
        $this->con = $con;
        $this->table = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function open($path = null, $name = null)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id)
    {
        // delete the record associated with this id
        $sql = "DELETE FROM $this->table WHERE $this->idCol = :id";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to delete a session: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        // delete the session records that have expired
        $sql = "DELETE FROM $this->table WHERE $this->timeCol < :time";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':time', time() - $lifetime, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to delete expired sessions: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($id)
    {
        $sql = "SELECT $this->dataCol FROM $this->table WHERE $this->idCol = :id";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();

            // We use fetchAll instead of fetchColumn to make sure the DB cursor gets closed
            $sessionRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if ($sessionRows) {
                return base64_decode($sessionRows[0][0]);
            }

            return '';
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to read the session data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data)
    {
        // Session data can contain non binary safe characters so we need to encode it.
        $encoded = base64_encode($data);

        // We use a MERGE SQL query when supported by the database.
        // Otherwise we have to use a transactional DELETE followed by INSERT to prevent duplicate entries under high concurrency.

        try {
            $mergeSql = $this->getMergeSql();

            if (null !== $mergeSql) {
                $mergeStmt = $this->con->prepare($mergeSql);
                $mergeStmt->bindParam(':id', $id, \PDO::PARAM_STR);
                $mergeStmt->bindParam(':data', $encoded, \PDO::PARAM_STR);
                $mergeStmt->bindValue(':time', time(), \PDO::PARAM_INT);
                $mergeStmt->execute();

                return true;
            }

            $this->con->beginTransaction();

            try {
                $deleteStmt = $this->con->prepare(
                    "DELETE FROM $this->table WHERE $this->idCol = :id"
                );
                $deleteStmt->bindParam(':id', $id, \PDO::PARAM_STR);
                $deleteStmt->execute();

                $insertStmt = $this->con->prepare(
                    "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)"
                );
                $insertStmt->bindParam(':id', $id, \PDO::PARAM_STR);
                $insertStmt->bindParam(':data', $encoded, \PDO::PARAM_STR);
                $insertStmt->bindValue(':time', time(), \PDO::PARAM_INT);
                $insertStmt->execute();

                $this->con->commit();
            } catch (\Exception $e) {
                $this->con->rollback();

                throw $e;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to write the session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) SQL query when supported by the database.
     *
     * @return string|null The SQL string or null when not supported
     */
    private function getMergeSql()
    {
        $platform = $pdoDriver = null;

        if ($this->con instanceof Connection) {
            $platform = $this->con->getDatabasePlatform();
        } elseif ($this->con instanceof PDOConnection) {
            $pdoDriver = $this->con->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }

        switch (true) {
            case $this->con instanceof MysqliConnection || $platform instanceof MySqlPlatform || 'mysql' === $pdoDriver:
                return "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) " .
                    "ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->timeCol = VALUES($this->timeCol)";
            case $this->con instanceof OCI8Connection || $platform instanceof OraclePlatform || 'oci' === $pdoDriver:
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->table USING DUAL ON ($this->idCol = :id) " .
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) " .
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data";
            case $this->con instanceof SQLSrvConnection || $platform instanceof SQLServerPlatform || 'sqlsrv' === $pdoDriver:
                // MS SQL Server requires MERGE be terminated by semicolon
                return "MERGE INTO $this->table USING (SELECT 'x' AS dummy) AS src ON ($this->idCol = :id) " .
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) " .
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data;";
            case $platform instanceof SqlitePlatform || 'sqlite' === $pdoDriver:
                return "INSERT OR REPLACE INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)";
        }

        return null;
    }
}
