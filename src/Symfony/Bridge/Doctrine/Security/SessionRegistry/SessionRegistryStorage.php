<?php

namespace Symfony\Bridge\Doctrine\Security\SessionRegistry;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Security\Http\Session\SessionInformation;
use Symfony\Component\Security\Http\Session\SessionRegistryStorageInterface;

class SessionRegistryStorage implements SessionRegistryStorageInterface
{
    protected $connection;
    protected $options;

    public function __construct(Connection $connection, array $options)
    {
        $this->connection   = $connection;
        $this->options      = $options;
    }

    /**
     * not implemented
     */
    public function getUsers()
    {
        throw new \BadMethodCallException("Not implemented.");
    }

    /**
     * Obtains the maintained information for one session.
     *
     * @param string $sessionId the session identifier key.
     * @return SessionInformation a SessionInformation object.
     */
    public function getSessionInformation($sessionId)
    {
        $statement = $this->connection->executeQuery(
            'SELECT * FROM '.$this->options['session_information_table_name'].' WHERE session_id = :session_id',
            array('session_id' => $sessionId)
        );

        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->instantiateSessionInformationFromResultSet($data) : null;
    }

    /**
     * Obtains the maintained information for one user.
     *
     * @param string $username The user identifier.
     * @param boolean $includeExpiredSessions.
     * @return array An array of SessionInformation objects.
     */
    public function getSessionInformations($username, $includeExpiredSessions = false)
    {
        $sessionInformations = array();

        $statement = $this->connection->executeQuery(
            'SELECT *
                FROM '.$this->options['session_information_table_name'].'
                WHERE username = :username'.($includeExpiredSessions ? '' : ' AND expired IS NULL ').'
                ORDER BY last_request DESC',
            array('username' => $username)
        );

        while ($data = $statement->fetch(\PDO::FETCH_ASSOC))
        {
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
        $statement = $this->connection->prepare(
            'INSERT INTO '.$this->options['session_information_table_name'].'
                (session_id, username, last_request, expired) VALUES(:session_id, :username, :last_request, :expired)
            ON DUPLICATE KEY
                UPDATE username=:username, last_request=:last_request, expired=:expired');

        $statement->bindValue('session_id', $sessionInformation->getSessionId());
        $statement->bindValue('username', $sessionInformation->getUsername());
        $statement->bindValue('last_request', $sessionInformation->getLastRequest(), 'datetime');
        $statement->bindValue('expired', $sessionInformation->getExpired(), 'datetime');
        $statement->execute();
    }

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    public function removeSessionInformation($sessionId)
    {
        $this->connection->delete($this->options['session_information_table_name'], array('session_id' => $sessionId));
    }

    private function instantiateSessionInformationFromResultSet($data)
    {
        return new $this->options['session_information_class_name'](
            $data['session_id'],
            $data['username'],
            (null == $data['last_request']) ? null : new \DateTime($data['last_request']),
            (null == $data['expired']) ? null : new \DateTime($data['expired'])
        );
    }
}
