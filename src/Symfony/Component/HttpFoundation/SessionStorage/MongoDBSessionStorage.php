<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

/**
 * MongoDBSessionStorage.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class MongoDBSessionStorage extends NativeSessionStorage
{
    protected $db;

    /**
     * @throws \InvalidArgumentException When "db_table" option is not provided
     */
    public function __construct(\MongoDB $db, array $options = array())
    {
        $this->db = $db;
        $options = array_merge(array(
            'id_field'   => 'sess_id',
            'data_field' => 'sess_data',
            'time_field' => 'sess_time',
        ), $options);

        if (!array_key_exists('collection', $options)) {
            throw new \InvalidArgumentException('You must provide the "collection" option for a MongoDBSessionStorage.');
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
     */
    public function sessionDestroy($id)
    {
        // get table/column
        $collection  = $this->options['collection'];
        $id_field = $this->options['id_field'];

        // delete the record associated with this id
        return $this->db->selectCollection($collection)->remove(array(
            $id_field => $id
        ));
    }

    /**
     * Cleans up old sessions.
     *
     * @param  int $lifetime  The lifetime of a session
     *
     * @return Boolean true, if old sessions have been cleaned, otherwise an exception is thrown
     */
    public function sessionGC($lifetime)
    {
        // get table/column
        $collection = $this->options['collection'];
        $time_field = $this->options['time_field'];

        // delete the record associated with this id
        $this->db->selectCollection($collection)->remove(array(
            $time_field => array('$lt' => new \MongoDate(time() - $lifetime))
        ));

        return true;
    }

    /**
     * Reads a session.
     *
     * @param  string $id  A session ID
     *
     * @return string      The session data if the session was read or created
     */
    public function sessionRead($id)
    {
        // get collection/fields
        $collection = $this->options['collection'];
        $data_field = $this->options['data_field'];
        $id_field   = $this->options['id_field'];
        $time_field = $this->options['time_field'];
        $collection = $this->db->selectCollection($collection);
        $result     = $collection->findOne(array($id_field => $id));

        if (null !== $result) {
            return $result[$data_field];
        }

        // session does not exist, create it
        $collection->insert(array(
            $id_field   => $id,
            $data_field => '',
            $time_field => new \MongoDate(),
        ));

        return '';
    }

    /**
     * Writes session data.
     *
     * @param  string $id    A session ID
     * @param  string $data  A serialized chunk of session data
     *
     * @return Boolean true, if the session was written
     */
    public function sessionWrite($id, $data)
    {
        // get table/column
        $collection = $this->options['collection'];
        $data_field = $this->options['data_field'];
        $id_field   = $this->options['id_field'];
        $time_field = $this->options['time_field'];

        $collection  = $this->db->selectCollection($collection);

        return $collection->update(
            array(
                $id_field => $id
            ),
            array('$set' => array(
                $data_field => $data,
                $time_field => new \MongoDate()
            ))
        );
    }
}
