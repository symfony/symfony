<?php

namespace Symfony\Framework\ProfilerBundle;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ProfilerStorage.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerStorage
{
    protected $token;
    protected $data;
    protected $store;

    public function __construct($store, $token = null)
    {
        $this->store = $store;
        $this->token = null === $token ? uniqid() : $token;
        $this->data = null;
    }

    public function hasData()
    {
        return null !== $this->data;
    }

    public function getData($name = null)
    {
        if (null === $this->data) {
            $this->data = $this->read();
        }

        if (null === $name) {
            return $this->data;
        }

        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function getToken()
    {
        return $this->token;
    }

    protected function read()
    {
        $db = $this->initDb();
        $args = array(':token' => $this->token);
        $data = $this->exec($db, 'SELECT data FROM data WHERE token = :token ORDER BY created_at DESC LIMIT 1', $args);
        $this->close($db);
        if (isset($data[0]['data'])) {
            return unserialize(pack('H*', $data[0]['data']));
        }
    }

    public function write($data)
    {
        $unpack = unpack('H*', serialize($data));
        $data = $unpack[1];

        $db = $this->initDb(false);
        $args = array(
            ':token' => $this->token,
            ':data' => (string) $data,
            ':time' => time()
        );
        $this->exec($db, 'INSERT INTO data (token, data, created_at) VALUES (:token, :data, :time)', $args);
        $this->close($db);
    }

    /**
     * @throws \RuntimeException When neither of SQLite or PDO_SQLite extension is enabled
     */
    protected function initDb($readOnly = true)
    {
        if (class_exists('\SQLite3')) {
            $flags  = $readOnly ? \SQLITE3_OPEN_READONLY : \SQLITE3_OPEN_READWRITE;
            $flags |= \SQLITE3_OPEN_CREATE;
            $db = new \SQLite3($this->store, $flags);
        } elseif (class_exists('\PDO') && in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $db = new \PDO('sqlite:'.$this->store);
        } else {
            throw new \RuntimeException('You need to enable either the SQLite or PDO_SQLite extension for the ProfilerBundle to run properly.');
        }

        $db->exec('CREATE TABLE IF NOT EXISTS data (token STRING, data STRING, created_at INTEGER)');
        $db->exec('CREATE INDEX IF NOT EXISTS data_data ON data (created_at)');

        return $db;
    }

    protected function exec($db, $query, array $args = array())
    {
        $return = array();
        $stmt = $db->prepare($query);

        if ($db instanceof \SQLite3) {
            foreach ($args as $arg => $val) {
                $stmt->bindValue($arg, $val, is_int($val) ? \SQLITE3_INTEGER : \SQLITE3_TEXT);
            }
            $res = $stmt->execute();
            while ($row = $res->fetchArray(\SQLITE3_ASSOC)) {
                $return[] = $row;
            }
            $res->finalize();
            $stmt->close();
        } else {
            foreach ($args as $arg => $val) {
                $stmt->bindValue($arg, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $return = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $return;
    }

    protected function close($db)
    {
        if ($db instanceof \SQLite3) {
            $db->close();
        }
    }

    public function purge($lifetime)
    {
        $db = $this->initDb(false);
        $args = array(':time' => time() - (int) $lifetime);
        $this->exec($db, 'DELETE FROM data WHERE created_at < :time', $args);
        $this->close($db);
    }
}
