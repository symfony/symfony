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

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Driver\Connection;

/**
 * DBAL based session storage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
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
    private $tableName;

    /**
     * Constructor.
     *
     * @param Connection $con       An instance of Connection.
     * @param string     $tableName Table name.
     */
    public function __construct(Connection $con, $tableName = 'sessions')
    {
        $this->con = $con;
        $this->tableName = $tableName;
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
        // do nothing
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id)
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
     * {@inheritdoc}
     */
    public function gc($lifetime)
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
     * {@inheritdoc}
     */
    public function read($id)
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
     * {@inheritdoc}
     */
    public function write($id, $data)
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
    *
    * @return Boolean
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
