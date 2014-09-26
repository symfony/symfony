<?php

namespace Symfony\Bridge\Doctrine\Security\SessionRegistry;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Security\Http\Session\SessionInformation;
use Symfony\Component\Security\Http\Session\SessionRegistryStorageInterface;

/**
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistryStorage implements SessionRegistryStorageInterface
{
    private $connection;
    private $table;

    /**
     * @param Connection $connection The DB connection
     * @param string     $table      The table name to store session information
     */
    public function __construct(Connection $connection, $table)
    {
        $this->connection = $connection;
        $this->table      = $table;
    }

    /**
     * Gets the stored information for the given session.
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
     * Gets the stored sessions information for the given username.
     *
     * @param  string                $username               The user identifier.
     * @param  bool                  $includeExpiredSessions If true, expired sessions information is included.
     * @return SessionInformations[] An array of SessionInformation objects.
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

        $statement->closeCursor();

        return $sessionInformations;
    }

    /**
     * Adds information for one session.
     *
     * @param SessionInformation a SessionInformation object.
     */
    public function setSessionInformation(SessionInformation $sessionInformation)
    {
        $mergeSql = $this->getMergeSql();

        if (null !== $mergeSql) {
            $this->connection->executeQuery(
                $mergeSql,
                array(
                    'session_id'   => $sessionInformation->getSessionId(),
                    'username'     => $sessionInformation->getUsername(),
                    'last_request' => $sessionInformation->getLastRequest(),
                    'expired'      => $sessionInformation->getExpired()
                ),
                array(
                    'last_request' => 'datetime',
                    'expired'      => 'datetime'
                )
            );

            return true;
        }

        $updateStmt = $this->connection->prepare(
            "UPDATE $this->table SET username=:username, last_request=:last_request, expired=:expired WHERE session_id = :session_id"
        );
        $updateStmt->bindValue('session_id', $sessionInformation->getSessionId());
        $updateStmt->bindValue('username', $sessionInformation->getUsername());
        $updateStmt->bindValue('last_request', $sessionInformation->getLastRequest(), 'datetime');
        $updateStmt->bindValue('expired', $sessionInformation->getExpired(), 'datetime');
        $updateStmt->execute();

        // When MERGE is not supported, like in Postgres, we have to use this approach that can result in
        // duplicate key errors when the same sessioninfo is written simultaneously. We can just catch such an
        // error and re-execute the update. This is similar to a serializable transaction with retry logic
        // on serialization failures but without the overhead and without possible false positives due to
        // longer gap locking.
        if (!$updateStmt->rowCount()) {
            try {
                $this->connection->insert(
                    $this->table,
                    array(
                        'session_id'   => $sessionInformation->getSessionId(),
                        'username'     => $sessionInformation->getUsername(),
                        'last_request' => $sessionInformation->getLastRequest(),
                        'expired'      => $sessionInformation->getExpired()
                    ),
                    array(
                        'last_request' => 'datetime',
                        'expired'      => 'datetime'
                    )
                );
            } catch (DBALException $e) {
                // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                if ($e->getPrevious() instanceof \PDOException && 0 === strpos($e->getPrevious()->getCode(), '23')) {
                    $updateStmt->execute();
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Deletes stored information of one session.
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
        switch ($this->connection->getDatabasePlatform()->getName()) {
            case 'mysql':
                return "INSERT INTO $this->table (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                    "ON DUPLICATE KEY UPDATE username = VALUES(username), last_request = VALUES(last_request), expired = VALUES(expired)";
            case 'oracle':
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->table USING DUAL ON (session_id= :session_id) ".
                    "WHEN NOT MATCHED THEN INSERT (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                    "WHEN MATCHED THEN UPDATE SET username = :username, last_request = :last_request, expired = :expired";
            case 'mssql':
                if ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLServer2008Platform || $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLServer2012Platform) {
                    // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                    // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                    return "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON (session_id = :session_id) ".
                        "WHEN NOT MATCHED THEN INSERT (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired) ".
                        "WHEN MATCHED THEN UPDATE SET username = :username, last_request = :last_request, expired = :expired;";
                }
            case 'sqlite':
                return "INSERT OR REPLACE INTO $this->table (session_id, username, last_request, expired) VALUES (:session_id, :username, :last_request, :expired)";
        }
    }
}
