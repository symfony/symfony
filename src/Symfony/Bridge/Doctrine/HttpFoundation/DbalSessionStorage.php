<?php

namespace Symfony\Bridge\Doctrine\HttpFoundation;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Symfony\Component\HttpFoundation\SessionStorage\AbstractSessionStorage;
use Symfony\Component\HttpFoundation\SessionStorage\SessionSaveHandlerInterface;
use Doctrine\DBAL\Driver\Connection;

/**
 * DBAL based session storage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DbalSessionStorage extends AbstractSessionStorage implements SessionSaveHandlerInterface
{
    /**
     * @var Connection
     */
    private $con;

    /**
     * @var string
     */
    private $tableName;

    /**
     *
     * @param Connection            $con        An instance of Connection.
     * @param string                $tableName  Table name.
     * @param array                 $options    Session configuration options
     */
    public function __construct(Connection $con, $tableName = 'sessions', array $options = array())
    {
        $this->con = $con;
        $this->tableName = $tableName;

        parent::__construct($options);
    }

    /**
     * Opens a session.
     *
     * @param  string $path  (ignored)
     * @param  string $name  (ignored)
     *
     * @return Boolean true, if the session was opened, otherwise an exception is thrown
     */
    public function openSession($path = null, $name = null)
    {
        return true;
    }

    /**
     * Closes a session.
     *
     * @return Boolean true, if the session was closed, otherwise false
     */
    public function closeSession()
    {
        // do nothing
        return true;
    }

    /**
     * Destroys a session.
     *
     * @param  string $id  A session ID
     *
     * @return Boolean   true, if the session was destroyed, otherwise an exception is thrown
     *
     * @throws \RuntimeException If the session cannot be destroyed
     */
    public function destroySession($id)
    {
        try {
            $this->con->executeQuery("DELETE FROM {$this->tableName} WHERE sess_id = :id", array(
                'id' => $id,
            ));
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * Cleans up old sessions.
     *
     * @param  int $lifetime  The lifetime of a session
     *
     * @return Boolean true, if old sessions have been cleaned, otherwise an exception is thrown
     *
     * @throws \RuntimeException If any old sessions cannot be cleaned
     */
    public function gcSession($lifetime)
    {
        try {
            $this->con->executeQuery("DELETE FROM {$this->tableName} WHERE sess_time < :time", array(
                'time' => time() - $lifetime,
            ));
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * Reads a session.
     *
     * @param  string $id  A session ID
     *
     * @return string      The session data if the session was read or created, otherwise an exception is thrown
     *
     * @throws \RuntimeException If the session cannot be read
     */
    public function readSession($id)
    {
        try {
            $data = $this->con->executeQuery("SELECT sess_data FROM {$this->tableName} WHERE sess_id = :id", array(
                'id' => $id,
            ))->fetchColumn();

            if (false !== $data) {
                return base64_decode($data);
            }

            // session does not exist, create it
            $this->createNewSession($id);

            return '';
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to read the session data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Writes session data.
     *
     * @param  string $id    A session ID
     * @param  string $data  A serialized chunk of session data
     *
     * @return Boolean true, if the session was written, otherwise an exception is thrown
     *
     * @throws \RuntimeException If the session data cannot be written
     */
    public function writeSession($id, $data)
    {
        $platform = $this->con->getDatabasePlatform();

        // this should maybe be abstracted in Doctrine DBAL
        if ($platform instanceof MySqlPlatform) {
            $sql = "INSERT INTO {$this->tableName} (sess_id, sess_data, sess_time) VALUES (%1\$s, %2\$s, %3\$d) "
                  ."ON DUPLICATE KEY UPDATE sess_data = VALUES(sess_data), sess_time = CASE WHEN sess_time = %3\$d THEN (VALUES(sess_time) + 1) ELSE VALUES(sess_time) END";
        } else {
            $sql = "UPDATE {$this->tableName} SET sess_data = %2\$s, sess_time = %3\$d WHERE sess_id = %1\$s";
        }

        try {
            $rowCount = $this->con->exec(sprintf(
                $sql,
                $this->con->quote($id),
                //session data can contain non binary safe characters so we need to encode it
                $this->con->quote(base64_encode($data)),
                time()
            ));

            if (!$rowCount) {
                // No session exists in the database to update. This happens when we have called
                // session_regenerate_id()
                $this->createNewSession($id, $data);
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to write the session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

   /**
    * Creates a new session with the given $id and $data
    *
    * @param string $id
    * @param string $data
    */
    private function createNewSession($id, $data = '')
    {
        $this->con->exec(sprintf("INSERT INTO {$this->tableName} (sess_id, sess_data, sess_time) VALUES (%s, %s, %d)",
            $this->con->quote($id),
            //session data can contain non binary safe characters so we need to encode it
            $this->con->quote(base64_encode($data)),
            time()
        ));

        return true;
    }
}
