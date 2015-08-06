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
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;

/**
 * DBAL based session storage.
 *
 * This implementation is very similar to Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
 * but uses a Doctrine connection and thus also works with non-PDO-based drivers like mysqli and OCI8.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class DbalSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var Connection
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
     * @param Connection $con       A connection
     * @param string     $tableName Table name
     */
    public function __construct(Connection $con, $tableName = 'sessions')
    {
        $this->con = $con;
        $this->table = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
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
    public function destroy($sessionId)
    {
        // delete the record associated with this id
        $sql = "DELETE FROM $this->table WHERE $this->idCol = :id";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to delete a session: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        // delete the session records that have expired
        $sql = "DELETE FROM $this->table WHERE $this->timeCol < :time";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindValue(':time', time() - $maxlifetime, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Exception was thrown when trying to delete expired sessions: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $sql = "SELECT $this->dataCol FROM $this->table WHERE $this->idCol = :id";

        try {
            $stmt = $this->con->prepare($sql);
            $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
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
    public function write($sessionId, $data)
    {
        $encoded = base64_encode($data);

        try {
            // We use a single MERGE SQL query when supported by the database.
            $mergeSql = $this->getMergeSql();

            if (null !== $mergeSql) {
                $mergeStmt = $this->con->prepare($mergeSql);
                $mergeStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
                $mergeStmt->bindParam(':data', $encoded, \PDO::PARAM_STR);
                $mergeStmt->bindValue(':time', time(), \PDO::PARAM_INT);

                //Oracle has a bug that will intermitently happen if you
                //have only 1 bind on a CLOB field for 2 different statements
                //(INSERT and UPDATE in this case)
                if ('oracle' == $this->con->getDatabasePlatform()->getName()) {
                    $mergeStmt->bindParam(':data2', $encoded, \PDO::PARAM_STR);
                }

                $mergeStmt->execute();

                return true;
            }

            $updateStmt = $this->con->prepare(
                "UPDATE $this->table SET $this->dataCol = :data, $this->timeCol = :time WHERE $this->idCol = :id"
            );
            $updateStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $updateStmt->bindParam(':data', $encoded, \PDO::PARAM_STR);
            $updateStmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $updateStmt->execute();

            // When MERGE is not supported, like in Postgres, we have to use this approach that can result in
            // duplicate key errors when the same session is written simultaneously. We can just catch such an
            // error and re-execute the update. This is similar to a serializable transaction with retry logic
            // on serialization failures but without the overhead and without possible false positives due to
            // longer gap locking.
            if (!$updateStmt->rowCount()) {
                try {
                    $insertStmt = $this->con->prepare(
                        "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)"
                    );
                    $insertStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
                    $insertStmt->bindParam(':data', $encoded, \PDO::PARAM_STR);
                    $insertStmt->bindValue(':time', time(), \PDO::PARAM_INT);
                    $insertStmt->execute();
                } catch (\Exception $e) {
                    $driverException = $e->getPrevious();
                    // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                    // DriverException only available since DBAL 2.5
                    if (
                        ($driverException instanceof DriverException && 0 === strpos($driverException->getSQLState(), '23')) ||
                        ($driverException instanceof \PDOException && 0 === strpos($driverException->getCode(), '23'))
                    ) {
                        $updateStmt->execute();
                    } else {
                        throw $e;
                    }
                }
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
        $platform = $this->con->getDatabasePlatform()->getName();

        switch ($platform) {
            case 'mysql':
                return "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->timeCol = VALUES($this->timeCol)";
            case 'oracle':
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->table USING DUAL ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data2, $this->timeCol = :time";
            case $this->con->getDatabasePlatform() instanceof SQLServer2008Platform:
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                return "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data, $this->timeCol = :time;";
            case 'sqlite':
                return "INSERT OR REPLACE INTO $this->table ($this->idCol, $this->dataCol, $this->timeCol) VALUES (:id, :data, :time)";
        }
    }
}
