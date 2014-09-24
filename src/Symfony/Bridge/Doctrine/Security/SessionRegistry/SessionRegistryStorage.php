<?php

namespace Symfony\Bridge\Doctrine\Security\SessionRegistry;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Http\Session\SessionInformation;
use Symfony\Component\Security\Http\Session\SessionRegistryStorageInterface;

/**
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistryStorage implements SessionRegistryStorageInterface
{
    protected $connection;
    protected $table;

    public function __construct(Connection $connection, $table)
    {
        $this->connection = $connection;
        $this->table      = $table;
    }

    /**
     * Obtains the maintained information for one session.
     *
     * @param  string             $sessionId the session identifier key.
     * @return SessionInformation a SessionInformation object.
     */
    public function getSessionInformation($sessionId)
    {
        $statement = $this->connection->executeQuery(
            'SELECT * FROM '.$this->table.' WHERE session_id = :session_id',
            array('session_id' => $sessionId)
        );

        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->instantiateSessionInformationFromResultSet($data) : null;
    }

    /**
     * Obtains the maintained information for one user.
     *
     * @param  string  $username                The user identifier.
     * @param  bool    $includeExpiredSessions.
     * @return array   An array of SessionInformation objects.
     */
    public function getSessionInformations($username, $includeExpiredSessions = false)
    {
        $sessionInformations = array();

        $statement = $this->connection->executeQuery(
            'SELECT *
                FROM '.$this->table.'
                WHERE username = :username'.($includeExpiredSessions ? '' : ' AND expired IS NULL ').'
                ORDER BY last_request DESC',
            array('username' => $username)
        );

        while ($data = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $sessionInformations[] = $this->instantiateSessionInformationFromResultSet($data);
        }

        return $sessionInformations;
    }

    /**
     * Adds information for one session.
     *
     * @param string $sessionId the session identifier key.
     * @param SessionInformation a SessionInformation object.
     */
    public function setSessionInformation(SessionInformation $sessionInformation)
    {
        $mergeSql = $this->getMergeSql();

        if (null !== $mergeSql) {
            $mergeStmt = $this->pdo->prepare($mergeSql);
            $mergeStmt->bindValue('session_id', $sessionInformation->getSessionId());
            $mergeStmt->bindValue('username', $sessionInformation->getUsername());
            $mergeStmt->bindValue('last_request', $sessionInformation->getLastRequest(), 'datetime');
            $mergeStmt->bindValue('expired', $sessionInformation->getExpired(), 'datetime');
            $mergeStmt->execute();

            return true;
        }

        $updateStmt = $this->pdo->prepare(
            "UPDATE $this->table SET username=:username, last_request=:last_request, expired=:expired WHERE session_id = :session_id"
        );
        $mergeStmt->bindValue('session_id', $sessionInformation->getSessionId());
        $mergeStmt->bindValue('username', $sessionInformation->getUsername());
        $mergeStmt->bindValue('last_request', $sessionInformation->getLastRequest(), 'datetime');
        $mergeStmt->bindValue('expired', $sessionInformation->getExpired(), 'datetime');
        $updateStmt->execute();

        // When MERGE is not supported, like in Postgres, we have to use this approach that can result in
        // duplicate key errors when the same sessioninfo is written simultaneously. We can just catch such an
        // error and re-execute the update. This is similar to a serializable transaction with retry logic
        // on serialization failures but without the overhead and without possible false positives due to
        // longer gap locking.
        if (!$updateStmt->rowCount()) {
            try {
                $insertStmt = $this->pdo->prepare(
                    "INTO $this->table (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired)"
                );
                $insertStmt->bindValue('session_id', $sessionInformation->getSessionId());
                $insertStmt->bindValue('username', $sessionInformation->getUsername());
                $insertStmt->bindValue('last_request', $sessionInformation->getLastRequest(), 'datetime');
                $insertStmt->bindValue('expired', $sessionInformation->getExpired(), 'datetime');
                $insertStmt->execute();
            } catch (\PDOException $e) {
                // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                if (0 === strpos($e->getCode(), '23')) {
                    $updateStmt->execute();
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    public function removeSessionInformation($sessionId)
    {
        $this->connection->delete($this->table, array('session_id' => $sessionId));
    }

    private function instantiateSessionInformationFromResultSet($data)
    {
        return new SessionInformation(
            $data['session_id'],
            $data['username'],
            null === $data['last_request'] ? null : new \DateTime($data['last_request']),
            null === $data['expired'] ? null : new \DateTime($data['expired'])
        );
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) SQL query when supported by the database.
     *
     * @return string|null The SQL string or null when not supported
     */
    private function getMergeSql()
    {
        switch ($this->connection->getDriver()->getName()) {
            case 'pdo_mysql':
                return "INSERT INTO $this->table (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                    "ON DUPLICATE KEY UPDATE username = VALUES(username), last_request = VALUES(last_request), expired = VALUES(expired)";
            case 'pdo_oracle':
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->table USING DUAL ON (session_id= :session_id) ".
                    "WHEN NOT MATCHED THEN INSERT (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                    "WHEN MATCHED THEN UPDATE SET username = :username, last_request = :last_request, expired = :expired";
            case 'pdo_sqlsrv':
                if (version_compare($this->connection->getWrappedConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION), '10', '>=')) {
                    // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                    // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                    return "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON (session_id = :session_id) ".
                        "WHEN NOT MATCHED THEN INSERT (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                        "WHEN MATCHED THEN UPDATE SET username = :username, last_request = :last_request, expired = :expired;";
                }
            case 'pdo_sqlite':
                return "INSERT OR REPLACE INTO $this->table (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired)";
        }
    }
}
