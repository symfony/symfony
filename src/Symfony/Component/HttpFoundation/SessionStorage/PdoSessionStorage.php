<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

/**
 * PdoSessionStorage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 */
class PdoSessionStorage extends NativeSessionStorage
{
    protected $db;

    /**
     * @throws \InvalidArgumentException When "db_table" option is not provided
     */
    public function __construct(\PDO $db, $options = null)
    {
        $this->db = $db;
        $options = array_merge(array(
            'db_id_col'   => 'sess_id',
            'db_data_col' => 'sess_data',
            'db_time_col' => 'sess_time',
        ), $options);

        if (!array_key_exists('db_table', $options)) {
            throw new \InvalidArgumentException('You must provide the "db_table" option for a PdoSessionStorage.');
        }

        parent::__construct($options);
    }

    /**
     * Starts the session.
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        // use this object as the session handler
        session_set_save_handler(
            array($this, 'sessionOpen'),
            array($this, 'sessionClose'),
            array($this, 'sessionRead'),
            array($this, 'sessionWrite'),
            array($this, 'sessionDestroy'),
            array($this, 'sessionGC')
        );

        parent::start();
    }

    /**
     * Opens a session.
     *
     * @param  string $path  (ignored)
     * @param  string $name  (ignored)
     *
     * @return Boolean true, if the session was opened, otherwise an exception is thrown
     */
    public function sessionOpen($path = null, $name = null)
    {
        return true;
    }

    /**
     * Closes a session.
     *
     * @return Boolean true, if the session was closed, otherwise false
     */
    public function sessionClose()
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
    public function sessionDestroy($id)
    {
        // get table/column
        $dbTable  = $this->options['db_table'];
        $dbIdCol = $this->options['db_id_col'];

        // delete the record associated with this id
        $sql = 'DELETE FROM '.$dbTable.' WHERE '.$dbIdCol.'= ?';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $id, \PDO::PARAM_STR);
            $stmt->execute();
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
    public function sessionGC($lifetime)
    {
        // get table/column
        $dbTable    = $this->options['db_table'];
        $dbTimeCol = $this->options['db_time_col'];

        // delete the record associated with this id
        $sql = 'DELETE FROM '.$dbTable.' WHERE '.$dbTimeCol.' < '.(time() - $lifetime);

        try {
            $this->db->query($sql);
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
    public function sessionRead($id)
    {
        // get table/columns
        $dbTable    = $this->options['db_table'];
        $dbDataCol = $this->options['db_data_col'];
        $dbIdCol   = $this->options['db_id_col'];
        $dbTimeCol = $this->options['db_time_col'];

        try {
            $sql = 'SELECT '.$dbDataCol.' FROM '.$dbTable.' WHERE '.$dbIdCol.'=?';

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $id, \PDO::PARAM_STR, 255);

            $stmt->execute();
            // it is recommended to use fetchAll so that PDO can close the DB cursor
            // we anyway expect either no rows, or one row with one column. fetchColumn, seems to be buggy #4777
            $sessionRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($sessionRows) == 1) {
                return $sessionRows[0][0];
            }

            // session does not exist, create it
            $this->createNewSession($id);

            return '';
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
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
    public function sessionWrite($id, $data)
    {
        // get table/column
        $dbTable    = $this->options['db_table'];
        $dbDataCol = $this->options['db_data_col'];
        $dbIdCol   = $this->options['db_id_col'];
        $dbTimeCol = $this->options['db_time_col'];

        $sql = 'UPDATE '.$dbTable.' SET '.$dbDataCol.' = ?, '.$dbTimeCol.' = '.time().' WHERE '.$dbIdCol.'= ?';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $data, \PDO::PARAM_STR);
            $stmt->bindParam(2, $id, \PDO::PARAM_STR);
            $stmt->execute();

            if (!$stmt->rowCount()) {
                // No session exists in the database to update. This happens when we have called
                // session_regenerate_id()
                $this->createNewSession($id, $data);
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(sprintf('PDOException was thrown when trying to manipulate session data: %s', $e->getMessage()), 0, $e);
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
        // get table/column
        $dbTable    = $this->options['db_table'];
        $dbDataCol = $this->options['db_data_col'];
        $dbIdCol   = $this->options['db_id_col'];
        $dbTimeCol = $this->options['db_time_col'];

        $sql = 'INSERT INTO '.$dbTable.'('.$dbIdCol.', '.$dbDataCol.', '.$dbTimeCol.') VALUES (?, ?, ?)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $id, \PDO::PARAM_STR);
        $stmt->bindValue(2, $data, \PDO::PARAM_STR);
        $stmt->bindValue(3, time(), \PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }
}
