<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

/**
 * A ProfilerStorage for Mysql
 *
 * @author Jan Schumann <js@schumann-it.com>
 */
class MysqlProfilerStorage extends PdoProfilerStorage
{
    /**
     * {@inheritdoc}
     */
    protected function initDb()
    {
        if (null === $this->db) {
            if ('mysql' !== substr($this->dsn, 0, 5)) {
                throw new \RuntimeException('Please check your configuration. You are trying to use Mysql with a wrong dsn. "' . $this->dsn . '"');
            }

            if (!class_exists('PDO') || !in_array('mysql', \PDO::getAvailableDrivers(), true)) {
                throw new \RuntimeException('You need to enable PDO_Mysql extension for the profiler to run properly.');
            }

            $db = new \PDO($this->dsn, $this->username, $this->password);
            $db->exec('CREATE TABLE IF NOT EXISTS sf_profiler_data (token VARCHAR(255) PRIMARY KEY, data LONGTEXT, ip VARCHAR(64), url VARCHAR(255), time INTEGER UNSIGNED, parent VARCHAR(255), created_at INTEGER UNSIGNED, KEY (created_at), KEY (ip), KEY (url), KEY (parent))');

            $this->db = $db;
        }

        return $this->db;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCriteria($ip, $url, $limit)
    {
        $criteria = array();
        $args = array();

        if ($ip = preg_replace('/[^\d\.]/', '', $ip)) {
            $criteria[] = 'ip LIKE :ip';
            $args[':ip'] = '%'.$ip.'%';
        }

        if ($url) {
            $criteria[] = 'url LIKE :url';
            $args[':url'] = '%'.addcslashes($url, '%_\\').'%';
        }

        return array($criteria, $args);
    }
}
