<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

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
        $args = array();

        if ($ip = preg_replace('/[^\d\.]/', '', $ip)) {
            $criteria[] = 'ip LIKE :ip';
            $args[':ip'] = '%'.$ip.'%';
        }

        if ($url) {
            $criteria[] = 'url LIKE :url ESCAPE "\"';
            $args[':url'] = '%'.addcslashes($url, '%_\\').'%';
        }

        $criteria = $criteria ? 'WHERE '.implode(' AND ', $criteria) : '';

        $db = $this->initDb();
        $tokens = $this->fetch($db, 'SELECT token, ip, url, time FROM data '.$criteria.' ORDER BY time DESC LIMIT '.((integer) $limit), $args);
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
        $data = $this->fetch($db, 'SELECT data, ip, url, time FROM data WHERE token = :token LIMIT 1', $args);
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
            ':token'        => $token,
            ':data'         => $data,
            ':ip'           => $ip,
            ':url'          => $url,
            ':time'         => $time,
            ':created_at'   => time(),
        );
        try {
            $this->exec($db, 'INSERT INTO data (token, data, ip, url, time, created_at) VALUES (:token, :data, :ip, :url, :time, :created_at)', $args);
            $this->cleanup();
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        $this->close($db);

        return $status;
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
        $this->exec($db, 'DELETE FROM data WHERE created_at < :time', array(':time' => time() - $this->lifetime));
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

        $db->exec('CREATE TABLE IF NOT EXISTS data (token STRING, data STRING, ip STRING, url STRING, time INTEGER, created_at INTEGER)');
        $db->exec('CREATE INDEX IF NOT EXISTS data_created_at ON data (created_at)');
        $db->exec('CREATE INDEX IF NOT EXISTS data_ip ON data (ip)');
        $db->exec('CREATE INDEX IF NOT EXISTS data_url ON data (url)');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS data_token ON data (token)');

        return $db;
    }

    protected function exec($db, $query, array $args = array())
    {
        $stmt = $db->prepare($query);

        if (false === $stmt) {
            throw new \RuntimeException('The database cannot successfully prepare the statement');
        }

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
            $success = $stmt->execute();
            if (!$success) {
                throw new \RuntimeException(sprintf('Error executing SQLite query "%s"', $query));
            }
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
