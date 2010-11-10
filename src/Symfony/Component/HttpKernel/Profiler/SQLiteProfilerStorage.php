<?php

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SQLiteProfilerStorage stores profiling information in a SQLite database.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SQLiteProfilerStorage implements ProfilerStorageInterface
{
    protected $store;
    protected $lifetime;

    /**
     * Constructor.
     *
     * @param string  $store    The path to the SQLite DB
     * @param integer $lifetime The lifetime to use for the purge
     */
    public function __construct($store, $lifetime = 86400)
    {
        $this->store = $store;
        $this->lifetime = (int) $lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit)
    {
        $criteria = array();

        if ($ip = preg_replace('/[^\d\.]/', '', $ip)) {
            $criteria[] = ' ip LIKE "%'.$ip.'%"';
        }

        if ($url) {
            $criteria[] = ' url LIKE "%'.$url.'%"';
        }

        $criteria = $criteria ? 'WHERE '.implode(' AND ', $criteria) : '';

        $db = $this->initDb();
        $tokens = $this->fetch($db, 'SELECT token, ip, url, time FROM data '.$criteria.' ORDER BY time DESC LIMIT '.((integer) $limit));
        $this->close($db);

        return $tokens;
    }

    /**
     * {@inheritdoc}
     */
    public function read($token)
    {
        $db = $this->initDb();
        $args = array(':token' => $token);
        $data = $this->fetch($db, 'SELECT data, ip, url, time FROM data WHERE token = :token ORDER BY time DESC LIMIT 1', $args);
        $this->close($db);
        if (isset($data[0]['data'])) {
            return array($data[0]['data'], $data[0]['ip'], $data[0]['url'], $data[0]['time']);
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($token, $data, $ip, $url, $time)
    {
        $db = $this->initDb();
        $args = array(
            ':token' => $token,
            ':data'  => $data,
            ':ip'    => $ip,
            ':url'   => $url,
            ':time'  => $time,
        );
        $this->exec($db, 'INSERT INTO data (token, data, ip, url, time) VALUES (:token, :data, :ip, :url, :time)', $args);
        $this->cleanup();
        $this->close($db);
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        $db = $this->initDb();
        $this->exec($db, 'DELETE FROM data');
        $this->close($db);
    }

    protected function cleanup()
    {
        $db = $this->initDb();
        $this->exec($db, 'DELETE FROM data WHERE time < :time', array(':time' => time() - $this->lifetime));
        $this->close($db);
    }

    /**
     * @throws \RuntimeException When neither of SQLite or PDO_SQLite extension is enabled
     */
    protected function initDb()
    {
        if (class_exists('SQLite3')) {
            $db = new \SQLite3($this->store, \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE);
        } elseif (class_exists('PDO') && in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $db = new \PDO('sqlite:'.$this->store);
        } else {
            throw new \RuntimeException('You need to enable either the SQLite or PDO_SQLite extension for the profiler to run properly.');
        }

        $db->exec('CREATE TABLE IF NOT EXISTS data (token STRING, data STRING, ip STRING, url STRING, time INTEGER)');
        $db->exec('CREATE INDEX IF NOT EXISTS data_data ON data (time)');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS data_token ON data (token)');

        return $db;
    }

    protected function exec($db, $query, array $args = array())
    {
        $stmt = $db->prepare($query);
        if ($db instanceof \SQLite3) {
            foreach ($args as $arg => $val) {
                $stmt->bindValue($arg, $val, is_int($val) ? \SQLITE3_INTEGER : \SQLITE3_TEXT);
            }

            $res = $stmt->execute();
            $res->finalize();
        } else {
            foreach ($args as $arg => $val) {
                $stmt->bindValue($arg, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
        }
    }

    protected function fetch($db, $query, array $args = array())
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
}
