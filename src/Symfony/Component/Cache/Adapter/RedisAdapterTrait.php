<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Predis\Connection\Aggregate\PredisCluster;
use Predis\Connection\Aggregate\RedisCluster;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait RedisAdapterTrait
{
    private $redis;
    private $namespace;

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        // When using a native Redis cluster, clearing the cache cannot work and always returns false.
        // Clearing the cache should then be done by any other means (e.g. by restarting the cluster).

        $hosts = array($this->redis);
        $evalArgs = array(array($namespace), 0);

        if ($this->redis instanceof \Predis\Client) {
            $evalArgs = array(0, $namespace);

            $connection = $this->redis->getConnection();
            if ($connection instanceof PredisCluster) {
                $hosts = array();
                foreach ($connection as $c) {
                    $hosts[] = new \Predis\Client($c);
                }
            } elseif ($connection instanceof RedisCluster) {
                return false;
            }
        } elseif ($this->redis instanceof \RedisArray) {
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof \RedisCluster) {
            return false;
        }
        foreach ($hosts as $host) {
            if (!isset($namespace[0])) {
                $host->flushDb();
            } else {
                // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
                // can hang your server when it is executed against large databases (millions of items).
                // Whenever you hit this scale, it is advised to deploy one Redis database per cache pool
                // instead of using namespaces, so that FLUSHDB is used instead.
                $host->eval("local keys=redis.call('KEYS',ARGV[1]..'*') for i=1,#keys,5000 do redis.call('DEL',unpack(keys,i,math.min(i+4999,#keys))) end", $evalArgs[0], $evalArgs[1]);
            }
        }

        return true;
    }

    private function setRedis($redisClient, $namespace)
    {
        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \RedisCluster && !$redisClient instanceof \Predis\Client) {
            throw new InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, is_object($redisClient) ? get_class($redisClient) : gettype($redisClient)));
        }
        $this->redis = $redisClient;
        $this->namespace = $namespace;
    }

    private function execute($command, $id, array $args = array(), $redis = null)
    {
        array_unshift($args, $id);
        call_user_func_array(array($redis ?: $this->redis, $command), $args);
    }

    private function pipeline(\Closure $callback)
    {
        $redis = $this->redis;

        try {
            if ($redis instanceof \Predis\Client) {
                $redis->pipeline(function ($pipe) use ($callback) {
                    $this->redis = $pipe;
                    $callback(array($this, 'execute'));
                });
            } elseif ($redis instanceof \RedisArray) {
                $connections = array();
                $callback(function ($command, $id, $args = array()) use (&$connections) {
                    if (!isset($connections[$h = $this->redis->_target($id)])) {
                        $connections[$h] = $this->redis->_instance($h);
                        $connections[$h]->multi(\Redis::PIPELINE);
                    }
                    $this->execute($command, $id, $args, $connections[$h]);
                });
                foreach ($connections as $c) {
                    $c->exec();
                }
            } else {
                $pipe = $redis->multi(\Redis::PIPELINE);
                try {
                    $callback(array($this, 'execute'));
                } finally {
                    if ($pipe) {
                        $redis->exec();
                    }
                }
            }
        } finally {
            $this->redis = $redis;
        }
    }
}
