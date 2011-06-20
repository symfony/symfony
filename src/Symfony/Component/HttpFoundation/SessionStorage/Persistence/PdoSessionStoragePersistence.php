<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

use Symfony\Component\HttpFoundation\SessionStorage\Persistence\AbstractSessionStoragePersistence;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * PdoSessionStorage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 */
class PdoSessionStoragePersistence extends AbstractSessionStoragePersistence
{
    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var array
     */
    private $dbOptions;

    /**
     * Constructor.
     *
     * @param \PDO  $db        A PDO instance
     * @param array $dbOptions An associative array of DB options
     *
     * @throws \InvalidArgumentException When "db_table" option is not provided
     *
     */
    public function __construct(\PDO $db, array $dbOptions = array(), EventDispatcherInterface $dispatcher)
    {
        if (!array_key_exists('db_table', $dbOptions)) {
            throw new \InvalidArgumentException('You must provide the "db_table" option for a PdoSessionStorage.');
        }

        $this->db = $db;
        $this->dbOptions = array_merge(array(
            'db_id_col'     => 'sess_id',
            'db_data_col'   => 'sess_data',
            'db_time_col'   => 'sess_time',
        ), $dbOptions);

        parent::__construct($dispatcher);
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function write($id, $data)
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];
        $dbTimeCol = $this->dbOptions['db_time_col'];

        $sql = ('mysql' === $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME))
                ? "INSERT INTO $dbTable ($dbIdCol, $dbDataCol, $dbTimeCol) VALUES (:id, :data, :time) "
                  . "ON DUPLICATE KEY UPDATE $dbDataCol = VALUES($dbDataCol), $dbTimeCol = CASE WHEN $dbTimeCol = :time THEN (VALUES($dbTimeCol) + 1) ELSE VALUES($dbTimeCol) END"
                : "UPDATE $dbTable SET $dbDataCol = :data, $dbTimeCol = :time WHERE $dbIdCol = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
            $stmt->bindParam(':data', $data, \PDO::PARAM_STR);
            $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();

            if (!$stmt->rowCount()) {
                // No session exists in the database to update. This happens when we have called
                // session_regenerate_id()
                $this->createNewSession($id, $data);
            }

            parent::write($id, $data);

        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($id)
    {
        // get table/column
        $dbTable  = $this->dbOptions['db_table'];
        $dbIdCol = $this->dbOptions['db_id_col'];

        // delete the record associated with this id
        $sql = "DELETE FROM $dbTable WHERE $dbIdCol = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($maxlifetime)
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbTimeCol = $this->dbOptions['db_time_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];
        $eventQueue = array();

        // delete the record associated with this id
        $sql = "SELECT * FROM $dbTable WHERE $dbTimeCol < (:time - $maxlifetime)";

        parent::gc($maxlifetime);

        try {
            $this->db->query($sql);
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();

            $sessionRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if($this->db->beginTransaction())
            {
                foreach($sessionRows as $row)
                {
                    $this->db->exec(sprintf("DELETE FROM `%s` WHERE `%s` = '%s'", $dbTable, $dbIdCol, $row[$dbIdCol]));
                    $eventQueue[] = $row[$dbIdCol];
                }

                $this->db->commit();

                foreach($eventQueue as $id)
                {
                    parent::destroy($id);
                }
            }
            else
            {
                throw new \RuntimeException('Could not start destroy transaction');
            }
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }


        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($id)
    {
        // get table/columns
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];

        try {
            $sql = "SELECT $dbDataCol FROM $dbTable WHERE $dbIdCol = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, \PDO::PARAM_STR, 255);

            $stmt->execute();
            // it is recommended to use fetchAll so that PDO can close the DB cursor
            // we anyway expect either no rows, or one row with one column. fetchColumn, seems to be buggy #4777
            $sessionRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($sessionRows) == 1) {
                return $sessionRows[0][0];
            }

            // session does not exist, create it
            $this->createNewSession($id);

            parent::read($id);

            return '';
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Creates a new session with the given $id and $data
     *
     * @param string $id
     * @param string $data
     */
    private function createNewSession($id, $data = '')
    {
        // get table/column
        $dbTable = $this->dbOptions['db_table'];
        $dbDataCol = $this->dbOptions['db_data_col'];
        $dbIdCol = $this->dbOptions['db_id_col'];
        $dbTimeCol = $this->dbOptions['db_time_col'];

        $sql = "INSERT INTO $dbTable ($dbIdCol, $dbDataCol, $dbTimeCol) VALUES (:id, :data, :time)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, \PDO::PARAM_STR);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }
}
