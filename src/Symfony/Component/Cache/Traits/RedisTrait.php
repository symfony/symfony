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

use Predis\Connection\Aggregate\ClusterInterface;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Response\Status;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

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
        'compression' => true,
        'tcp_keepalive' => 0,
        'lazy' => null,
        'redis_cluster' => false,
        'dbindex' => 0,
        'failover' => 'none',
    );
    private $redis;
    private $marshaller;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    private function init($redisClient, $namespace, $defaultLifetime, ?MarshallerInterface $marshaller)
    {
        parent::__construct($namespace, $defaultLifetime);

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \RedisCluster && !$redisClient instanceof \Predis\Client && !$redisClient instanceof RedisProxy && !$redisClient instanceof RedisClusterProxy) {
            throw new InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given.', __METHOD__, \is_object($redisClient) ? \get_class($redisClient) : \gettype($redisClient)));
        }
        $this->redis = $redisClient;
        $this->marshaller = $marshaller ?? new DefaultMarshaller();
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
     * @return \Redis|\RedisCluster|\Predis\Client According to the "class" option
     */
    public static function createConnection($dsn, array $options = array())
    {
        if (0 !== strpos($dsn, 'redis:')) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s does not start with "redis:".', $dsn));
        }

        if (!\extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            throw new CacheException(sprintf('Cannot find the "redis" extension nor the "predis/predis" package: %s', $dsn));
        }

        $params = preg_replace_callback('#^redis:(//)?(?:(?:[^:@]*+:)?([^@]*+)@)?#', function ($m) use (&$auth) {
            if (isset($m[2])) {
                $auth = $m[2];
            }

            return 'file:'.($m[1] ?? '');
        }, $dsn);

        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
        }

        $query = $hosts = array();

        if (isset($params['query'])) {
            parse_str($params['query'], $query);

            if (isset($query['host'])) {
                if (!\is_array($hosts = $query['host'])) {
                    throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
                }
                foreach ($hosts as $host => $parameters) {
                    if (\is_string($parameters)) {
                        parse_str($parameters, $parameters);
                    }
                    if (false === $i = strrpos($host, ':')) {
                        $hosts[$host] = array('scheme' => 'tcp', 'host' => $host, 'port' => 6379) + $parameters;
                    } elseif ($port = (int) substr($host, 1 + $i)) {
                        $hosts[$host] = array('scheme' => 'tcp', 'host' => substr($host, 0, $i), 'port' => $port) + $parameters;
                    } else {
                        $hosts[$host] = array('scheme' => 'unix', 'path' => substr($host, 0, $i)) + $parameters;
                    }
                }
                $hosts = array_values($hosts);
            }
        }

        if (isset($params['host']) || isset($params['path'])) {
            if (!isset($params['dbindex']) && isset($params['path']) && preg_match('#/(\d+)$#', $params['path'], $m)) {
                $params['dbindex'] = $m[1];
                $params['path'] = substr($params['path'], 0, -\strlen($m[0]));
            }

            if (isset($params['host'])) {
                array_unshift($hosts, array('scheme' => 'tcp', 'host' => $params['host'], 'port' => $params['port'] ?? 6379));
            } else {
                array_unshift($hosts, array('scheme' => 'unix', 'path' => $params['path']));
            }
        }

        if (!$hosts) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s', $dsn));
        }

        $params += $query + $options + self::$defaultConnectionOptions;

        if (null === $params['class'] && \extension_loaded('redis')) {
            $class = $params['redis_cluster'] ? \RedisCluster::class : (1 < \count($hosts) ? \RedisArray::class : \Redis::class);
        } else {
            $class = null === $params['class'] ? \Predis\Client::class : $params['class'];
        }

        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();

            $initializer = function ($redis) use ($connect, $params, $dsn, $auth, $hosts) {
                try {
                    @$redis->{$connect}($hosts[0]['host'], $hosts[0]['port'], $params['timeout'], (string) $params['persistent_id'], $params['retry_interval']);
                } catch (\RedisException $e) {
                    throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e->getMessage(), $dsn));
                }

                set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
                $isConnected = $redis->isConnected();
                restore_error_handler();
                if (!$isConnected) {
                    $error = preg_match('/^Redis::p?connect\(\): (.*)/', $error, $error) ? sprintf(' (%s)', $error[1]) : '';
                    throw new InvalidArgumentException(sprintf('Redis connection failed%s: %s', $error, $dsn));
                }

                if ((null !== $auth && !$redis->auth($auth))
                    || ($params['dbindex'] && !$redis->select($params['dbindex']))
                    || ($params['read_timeout'] && !$redis->setOption(\Redis::OPT_READ_TIMEOUT, $params['read_timeout']))
                ) {
                    $e = preg_replace('/^ERR /', '', $redis->getLastError());
                    throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e, $dsn));
                }

                if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                    $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
                if ($params['compression'] && \defined('Redis::COMPRESSION_LZF')) {
                    $redis->setOption(\Redis::OPT_COMPRESSION, \Redis::COMPRESSION_LZF);
                }

                return true;
            };

            if ($params['lazy']) {
                $redis = new RedisProxy($redis, $initializer);
            } else {
                $initializer($redis);
            }
        } elseif (is_a($class, \RedisArray::class, true)) {
            foreach ($hosts as $i => $host) {
                $hosts[$i] = 'tcp' === $host['scheme'] ? $host['host'].':'.$host['port'] : $host['path'];
            }
            $params['lazy_connect'] = $params['lazy'] ?? true;
            $params['connect_timeout'] = $params['timeout'];

            try {
                $redis = new $class($hosts, $params);
            } catch (\RedisClusterException $e) {
                throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e->getMessage(), $dsn));
            }

            if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }
            if ($params['compression'] && \defined('Redis::COMPRESSION_LZF')) {
                $redis->setOption(\Redis::OPT_COMPRESSION, \Redis::COMPRESSION_LZF);
            }
        } elseif (is_a($class, \RedisCluster::class, true)) {
            $initializer = function () use ($class, $params, $dsn, $hosts) {
                foreach ($hosts as $i => $host) {
                    $hosts[$i] = 'tcp' === $host['scheme'] ? $host['host'].':'.$host['port'] : $host['path'];
                }

                try {
                    $redis = new $class(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) $params['persistent']);
                } catch (\RedisClusterException $e) {
                    throw new InvalidArgumentException(sprintf('Redis connection failed (%s): %s', $e->getMessage(), $dsn));
                }

                if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                    $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
                if ($params['compression'] && \defined('Redis::COMPRESSION_LZF')) {
                    $redis->setOption(\Redis::OPT_COMPRESSION, \Redis::COMPRESSION_LZF);
                }
                switch ($params['failover']) {
                    case 'error': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR); break;
                    case 'distribute': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE); break;
                    case 'slaves': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE_SLAVES); break;
                }

                return $redis;
            };

            $redis = $params['lazy'] ? new RedisClusterProxy($initializer) : $initializer();
        } elseif (is_a($class, \Predis\Client::class, true)) {
            if ($params['redis_cluster']) {
                $params['cluster'] = 'redis';
            }
            $params += array('parameters' => array());
            $params['parameters'] += array(
                'persistent' => $params['persistent'],
                'timeout' => $params['timeout'],
                'read_write_timeout' => $params['read_timeout'],
                'tcp_nodelay' => true,
            );
            if ($params['dbindex']) {
                $params['parameters']['database'] = $params['dbindex'];
            }
            if (null !== $auth) {
                $params['parameters']['password'] = $auth;
            }
            if (1 === \count($hosts) && !$params['redis_cluster']) {
                $hosts = $hosts[0];
            } elseif (\in_array($params['failover'], array('slaves', 'distribute'), true) && !isset($params['replication'])) {
                $params['replication'] = true;
                $hosts[0] += array('alias' => 'master');
            }

            $redis = new $class($hosts, array_diff_key($params, self::$defaultConnectionOptions));
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Redis", "RedisArray", "RedisCluster" nor "Predis\Client".', $class));
        } else {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        return $redis;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        if (!$ids) {
            return array();
        }

        $result = array();

        if ($this->redis instanceof \Predis\Client) {
            $values = $this->pipeline(function () use ($ids) {
                foreach ($ids as $id) {
                    yield 'get' => array($id);
                }
            });
        } else {
            $values = array_combine($ids, $this->redis->mget($ids));
        }

        foreach ($values as $id => $v) {
            if ($v) {
                $result[$id] = $this->marshaller->unmarshall($v);
            }
        }

        return $result;
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
        $cleared = true;
        $hosts = array($this->redis);
        $evalArgs = array(array($namespace), 0);

        if ($this->redis instanceof \Predis\Client) {
            $evalArgs = array(0, $namespace);

            $connection = $this->redis->getConnection();
            if ($connection instanceof ClusterInterface && $connection instanceof \Traversable) {
                $hosts = array();
                foreach ($connection as $c) {
                    $hosts[] = new \Predis\Client($c);
                }
            }
        } elseif ($this->redis instanceof \RedisArray) {
            $hosts = array();
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof RedisClusterProxy || $this->redis instanceof \RedisCluster) {
            $hosts = array();
            foreach ($this->redis->_masters() as $host) {
                $hosts[] = $h = new \Redis();
                $h->connect($host[0], $host[1]);
            }
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
                    $this->doDelete($keys);
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
        if (!$ids) {
            return true;
        }

        if ($this->redis instanceof \Predis\Client) {
            $this->pipeline(function () use ($ids) {
                foreach ($ids as $id) {
                    yield 'del' => array($id);
                }
            })->rewind();
        } else {
            $this->redis->del($ids);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $results = $this->pipeline(function () use ($values, $lifetime) {
            foreach ($values as $id => $value) {
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

        if ($this->redis instanceof RedisClusterProxy || $this->redis instanceof \RedisCluster || ($this->redis instanceof \Predis\Client && $this->redis->getConnection() instanceof RedisCluster)) {
            // phpredis & predis don't support pipelining with RedisCluster
            // see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#pipelining
            // see https://github.com/nrk/predis/issues/267#issuecomment-123781423
            $results = array();
            foreach ($generator() as $command => $args) {
                $results[] = $this->redis->{$command}(...$args);
                $ids[] = $args[0];
            }
        } elseif ($this->redis instanceof \Predis\Client) {
            $results = $this->redis->pipeline(function ($redis) use ($generator, &$ids) {
                foreach ($generator() as $command => $args) {
                    $redis->{$command}(...$args);
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
                $connections[$h][0]->{$command}(...$args);
                $results[] = array($h, ++$connections[$h][1]);
                $ids[] = $args[0];
            }
            foreach ($connections as $h => $c) {
                $connections[$h] = $c[0]->exec();
            }
            foreach ($results as $k => list($h, $c)) {
                $results[$k] = $connections[$h][$c];
            }
        } else {
            $this->redis->multi(\Redis::PIPELINE);
            foreach ($generator() as $command => $args) {
                $this->redis->{$command}(...$args);
                $ids[] = $args[0];
            }
            $results = $this->redis->exec();
        }

        foreach ($ids as $k => $id) {
            yield $id => $results[$k];
        }
    }
}
