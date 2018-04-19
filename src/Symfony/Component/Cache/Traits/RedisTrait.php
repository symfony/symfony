<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Predis\Connection\Factory;
use Predis\Connection\Aggregate\ClusterInterface;
use Predis\Connection\Aggregate\PredisCluster;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Response\Status;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait RedisTrait
{
    private static $defaultConnectionOptions = array(
        'class' => null,
        'persistent' => 0,
        'persistent_id' => null,
        'timeout' => 30,
        'read_timeout' => 0,
        'retry_interval' => 0,
        'lazy' => false,
    );
    private $redis;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    public function init($redisClient, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if ($redisClient instanceof \RedisCluster) {
            $this->enableVersioning();
        } elseif (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \Predis\Client && !$redisClient instanceof RedisProxy) {
            throw new InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, is_object($redisClient) ? get_class($redisClient) : gettype($redisClient)));
        }
        $this->redis = $redisClient;
    }

    /**
     * Creates a Redis connection using a DSN configuration.
     *
     * Example DSN:
     *   - redis://localhost
     *   - redis://example.com:1234
     *   - redis://secret@example.com/13
     *   - redis:///var/run/redis.sock
     *   - redis://secret@/var/run/redis.sock/13
     *
     * @param string $dsn
     * @param array  $options See self::$defaultConnectionOptions
     *
     * @throws InvalidArgumentException when the DSN is invalid
     *
     * @return \Redis|\Predis\Client According to the "class" option
     */
    public static function createConnection($dsn, array $options = array())
    {
        if (0 !== strpos($dsn, 'redis://')) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s does not start with "redis://"', $dsn));
        }
        $params = preg_replace_callback('#^redis://(?:(?:[^:@]*+:)?([^@]*+)@)?#', function ($m) use (&$auth) {
            if (isset($m[1])) {
                $auth = $m[1];
            }

            return 'file://';
        }, $dsn);
        if (false === $params = parse_url($params)) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
        }
        if (!isset($params['host']) && !isset($params['path'])) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
        }
        if (isset($params['path']) && preg_match('#/(\d+)$#', $params['path'], $m)) {
            $params['dbindex'] = $m[1];
            $params['path'] = substr($params['path'], 0, -strlen($m[0]));
        }
        if (isset($params['host'])) {
            $scheme = 'tcp';
        } else {
            $scheme = 'unix';
        }
        $params += array(
            'host' => isset($params['host']) ? $params['host'] : $params['path'],
            'port' => isset($params['host']) ? 6379 : null,
            'dbindex' => 0,
        );
        if (isset($params['query'])) {
            parse_str($params['query'], $query);
            $params += $query;
        }
        $params += $options + self::$defaultConnectionOptions;
        if (null === $params['class'] && !extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            throw new CacheException(sprintf('Cannot find the "redis" extension, and "predis/predis" is not installed: %s', $dsn));
        }
        $class = null === $params['class'] ? (extension_loaded('redis') ? \Redis::class : \Predis\Client::class) : $params['class'];

        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();

            $initializer = function ($redis) use ($connect, $params, $dsn, $auth) {
                try {
                    @$redis->{$connect}($params['host'], $params['port'], $params['timeout'], $params['persistent_id'], $params['retry_interval']);
                } catch (\RedisException $e) {
                    throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e->getMessage(), $dsn));
                }

                if (@!$redis->isConnected()) {
                    $e = ($e = error_get_last()) && preg_match('/^Redis::p?connect\(\): (.*)/', $e['message'], $e) ? sprintf(' (%s)', $e[1]) : '';
                    throw new InvalidArgumentException(sprintf('Redis connection failed%s: %s', $e, $dsn));
                }

                if ((null !== $auth && !$redis->auth($auth))
                    || ($params['dbindex'] && !$redis->select($params['dbindex']))
                    || ($params['read_timeout'] && !$redis->setOption(\Redis::OPT_READ_TIMEOUT, $params['read_timeout']))
                ) {
                    $e = preg_replace('/^ERR /', '', $redis->getLastError());
                    throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e, $dsn));
                }

                return true;
            };

            if ($params['lazy']) {
                $redis = new RedisProxy($redis, $initializer);
            } else {
                $initializer($redis);
            }
        } elseif (is_a($class, \Predis\Client::class, true)) {
            $params['scheme'] = $scheme;
            $params['database'] = $params['dbindex'] ?: null;
            $params['password'] = $auth;
            $redis = new $class((new Factory())->create($params));
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Redis" or "Predis\Client"', $class));
        } else {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist', $class));
        }

        return $redis;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        if ($ids) {
            $values = $this->pipeline(function () use ($ids) {
                foreach ($ids as $id) {
                    yield 'get' => array($id);
                }
            });
            foreach ($values as $id => $v) {
                if ($v) {
                    yield $id => parent::unserialize($v);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return (bool) $this->redis->exists($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        // When using a native Redis cluster, clearing the cache is done by versioning in AbstractTrait::clear().
        // This means old keys are not really removed until they expire and may need gargage collection.

        $cleared = true;
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
            $hosts = array();
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof \RedisCluster) {
            return false;
        }
        foreach ($hosts as $host) {
            if (!isset($namespace[0])) {
                $cleared = $host->flushDb() && $cleared;
                continue;
            }

            $info = $host->info('Server');
            $info = isset($info['Server']) ? $info['Server'] : $info;

            if (!version_compare($info['redis_version'], '2.8', '>=')) {
                // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
                // can hang your server when it is executed against large databases (millions of items).
                // Whenever you hit this scale, you should really consider upgrading to Redis 2.8 or above.
                $cleared = $host->eval("local keys=redis.call('KEYS',ARGV[1]..'*') for i=1,#keys,5000 do redis.call('DEL',unpack(keys,i,math.min(i+4999,#keys))) end return 1", $evalArgs[0], $evalArgs[1]) && $cleared;
                continue;
            }

            $cursor = null;
            do {
                $keys = $host instanceof \Predis\Client ? $host->scan($cursor, 'MATCH', $namespace.'*', 'COUNT', 1000) : $host->scan($cursor, $namespace.'*', 1000);
                if (isset($keys[1]) && \is_array($keys[1])) {
                    $cursor = $keys[0];
                    $keys = $keys[1];
                }
                if ($keys) {
                    $host->del($keys);
                }
            } while ($cursor = (int) $cursor);
        }

        return $cleared;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        if ($ids) {
            $this->redis->del($ids);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $serialized = array();
        $failed = array();

        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        if (!$serialized) {
            return $failed;
        }

        $results = $this->pipeline(function () use ($serialized, $lifetime) {
            foreach ($serialized as $id => $value) {
                if (0 >= $lifetime) {
                    yield 'set' => array($id, $value);
                } else {
                    yield 'setEx' => array($id, $lifetime, $value);
                }
            }
        });
        foreach ($results as $id => $result) {
            if (true !== $result && (!$result instanceof Status || $result !== Status::get('OK'))) {
                $failed[] = $id;
            }
        }

        return $failed;
    }

    private function pipeline(\Closure $generator)
    {
        $ids = array();

        if ($this->redis instanceof \Predis\Client && !$this->redis->getConnection() instanceof ClusterInterface) {
            $results = $this->redis->pipeline(function ($redis) use ($generator, &$ids) {
                foreach ($generator() as $command => $args) {
                    call_user_func_array(array($redis, $command), $args);
                    $ids[] = $args[0];
                }
            });
        } elseif ($this->redis instanceof \RedisArray) {
            $connections = $results = $ids = array();
            foreach ($generator() as $command => $args) {
                if (!isset($connections[$h = $this->redis->_target($args[0])])) {
                    $connections[$h] = array($this->redis->_instance($h), -1);
                    $connections[$h][0]->multi(\Redis::PIPELINE);
                }
                call_user_func_array(array($connections[$h][0], $command), $args);
                $results[] = array($h, ++$connections[$h][1]);
                $ids[] = $args[0];
            }
            foreach ($connections as $h => $c) {
                $connections[$h] = $c[0]->exec();
            }
            foreach ($results as $k => list($h, $c)) {
                $results[$k] = $connections[$h][$c];
            }
        } elseif ($this->redis instanceof \RedisCluster || ($this->redis instanceof \Predis\Client && $this->redis->getConnection() instanceof ClusterInterface)) {
            // phpredis & predis don't support pipelining with RedisCluster
            // see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#pipelining
            // see https://github.com/nrk/predis/issues/267#issuecomment-123781423
            $results = array();
            foreach ($generator() as $command => $args) {
                $results[] = call_user_func_array(array($this->redis, $command), $args);
                $ids[] = $args[0];
            }
        } else {
            $this->redis->multi(\Redis::PIPELINE);
            foreach ($generator() as $command => $args) {
                call_user_func_array(array($this->redis, $command), $args);
                $ids[] = $args[0];
            }
            $results = $this->redis->exec();
        }

        foreach ($ids as $k => $id) {
            yield $id => $results[$k];
        }
    }
}
