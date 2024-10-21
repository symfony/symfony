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

use Predis\Command\Redis\UNLINK;
use Predis\Connection\Aggregate\ClusterInterface;
use Predis\Connection\Aggregate\RedisCluster;
use Predis\Connection\Aggregate\ReplicationInterface;
use Predis\Response\ErrorInterface;
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
    private static $defaultConnectionOptions = [
        'class' => null,
        'persistent' => 0,
        'persistent_id' => null,
        'timeout' => 30,
        'read_timeout' => 0,
        'retry_interval' => 0,
        'tcp_keepalive' => 0,
        'lazy' => null,
        'redis_cluster' => false,
        'redis_sentinel' => null,
        'dbindex' => 0,
        'failover' => 'none',
        'ssl' => null, // see https://php.net/context.ssl
    ];
    private $redis;
    private $marshaller;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|RedisProxy|RedisClusterProxy $redis
     */
    private function init($redis, string $namespace, int $defaultLifetime, ?MarshallerInterface $marshaller)
    {
        parent::__construct($namespace, $defaultLifetime);

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }

        if (!$redis instanceof \Redis && !$redis instanceof \RedisArray && !$redis instanceof \RedisCluster && !$redis instanceof \Predis\ClientInterface && !$redis instanceof RedisProxy && !$redis instanceof RedisClusterProxy) {
            throw new InvalidArgumentException(sprintf('"%s()" expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\ClientInterface, "%s" given.', __METHOD__, get_debug_type($redis)));
        }

        if ($redis instanceof \Predis\ClientInterface && $redis->getOptions()->exceptions) {
            $options = clone $redis->getOptions();
            \Closure::bind(function () { $this->options['exceptions'] = false; }, $options, $options)();
            $redis = new $redis($redis->getConnection(), $options);
        }

        $this->redis = $redis;
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
     * @param array $options See self::$defaultConnectionOptions
     *
     * @return \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy|\Predis\ClientInterface According to the "class" option
     *
     * @throws InvalidArgumentException when the DSN is invalid
     */
    public static function createConnection(string $dsn, array $options = [])
    {
        if (str_starts_with($dsn, 'redis:')) {
            $scheme = 'redis';
        } elseif (str_starts_with($dsn, 'rediss:')) {
            $scheme = 'rediss';
        } else {
            throw new InvalidArgumentException('Invalid Redis DSN: it does not start with "redis[s]:".');
        }

        if (!\extension_loaded('redis') && !class_exists(\Predis\Client::class)) {
            throw new CacheException('Cannot find the "redis" extension nor the "predis/predis" package.');
        }

        $params = preg_replace_callback('#^'.$scheme.':(//)?(?:(?:[^:@]*+:)?([^@]*+)@)?#', function ($m) use (&$auth) {
            if (isset($m[2])) {
                $auth = rawurldecode($m[2]);

                if ('' === $auth) {
                    $auth = null;
                }
            }

            return 'file:'.($m[1] ?? '');
        }, $dsn);

        if (false === $params = parse_url($params)) {
            throw new InvalidArgumentException('Invalid Redis DSN.');
        }

        $query = $hosts = [];

        $tls = 'rediss' === $scheme;
        $tcpScheme = $tls ? 'tls' : 'tcp';

        if (isset($params['query'])) {
            parse_str($params['query'], $query);

            if (isset($query['host'])) {
                if (!\is_array($hosts = $query['host'])) {
                    throw new InvalidArgumentException('Invalid Redis DSN: query parameter "host" must be an array.');
                }
                foreach ($hosts as $host => $parameters) {
                    if (\is_string($parameters)) {
                        parse_str($parameters, $parameters);
                    }
                    if (false === $i = strrpos($host, ':')) {
                        $hosts[$host] = ['scheme' => $tcpScheme, 'host' => $host, 'port' => 6379] + $parameters;
                    } elseif ($port = (int) substr($host, 1 + $i)) {
                        $hosts[$host] = ['scheme' => $tcpScheme, 'host' => substr($host, 0, $i), 'port' => $port] + $parameters;
                    } else {
                        $hosts[$host] = ['scheme' => 'unix', 'path' => substr($host, 0, $i)] + $parameters;
                    }
                }
                $hosts = array_values($hosts);
            }
        }

        if (isset($params['host']) || isset($params['path'])) {
            if (!isset($params['dbindex']) && isset($params['path'])) {
                if (preg_match('#/(\d+)?$#', $params['path'], $m)) {
                    $params['dbindex'] = $m[1] ?? '0';
                    $params['path'] = substr($params['path'], 0, -\strlen($m[0]));
                } elseif (isset($params['host'])) {
                    throw new InvalidArgumentException('Invalid Redis DSN: query parameter "dbindex" must be a number.');
                }
            }

            if (isset($params['host'])) {
                array_unshift($hosts, ['scheme' => $tcpScheme, 'host' => $params['host'], 'port' => $params['port'] ?? 6379]);
            } else {
                array_unshift($hosts, ['scheme' => 'unix', 'path' => $params['path']]);
            }
        }

        if (!$hosts) {
            throw new InvalidArgumentException('Invalid Redis DSN: missing host.');
        }

        $params += $query + $options + self::$defaultConnectionOptions;

        if (isset($params['redis_sentinel']) && !class_exists(\Predis\Client::class) && !class_exists(\RedisSentinel::class)) {
            throw new CacheException('Redis Sentinel support requires the "predis/predis" package or the "redis" extension v5.2 or higher.');
        }

        if (isset($params['lazy'])) {
            $params['lazy'] = filter_var($params['lazy'], \FILTER_VALIDATE_BOOLEAN);
        }
        $params['redis_cluster'] = filter_var($params['redis_cluster'], \FILTER_VALIDATE_BOOLEAN);

        if ($params['redis_cluster'] && isset($params['redis_sentinel'])) {
            throw new InvalidArgumentException('Cannot use both "redis_cluster" and "redis_sentinel" at the same time.');
        }

        if (null === $params['class'] && \extension_loaded('redis')) {
            $class = $params['redis_cluster'] ? \RedisCluster::class : (1 < \count($hosts) && !isset($params['redis_sentinel']) ? \RedisArray::class : \Redis::class);
        } else {
            $class = $params['class'] ?? \Predis\Client::class;

            if (isset($params['redis_sentinel']) && !is_a($class, \Predis\Client::class, true) && !class_exists(\RedisSentinel::class)) {
                throw new CacheException(sprintf('Cannot use Redis Sentinel: class "%s" does not extend "Predis\Client" and ext-redis >= 5.2 not found.', $class));
            }
        }

        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();

            $initializer = static function ($redis) use ($connect, $params, $auth, $hosts, $tls) {
                $hostIndex = 0;
                do {
                    $host = $hosts[$hostIndex]['host'] ?? $hosts[$hostIndex]['path'];
                    $port = $hosts[$hostIndex]['port'] ?? 0;
                    $passAuth = \defined('Redis::OPT_NULL_MULTIBULK_AS_NULL') && isset($params['auth']);
                    $address = false;

                    if (isset($hosts[$hostIndex]['host']) && $tls) {
                        $host = 'tls://'.$host;
                    }

                    if (!isset($params['redis_sentinel'])) {
                        break;
                    }

                    if (version_compare(phpversion('redis'), '6.0.0', '>=')) {
                        $options = [
                            'host' => $host,
                            'port' => $port,
                            'connectTimeout' => $params['timeout'],
                            'persistent' => $params['persistent_id'],
                            'retryInterval' => $params['retry_interval'],
                            'readTimeout' => $params['read_timeout'],
                        ];

                        if ($passAuth) {
                            $options['auth'] = $params['auth'];
                        }

                        $sentinel = new \RedisSentinel($options);
                    } else {
                        $extra = $passAuth ? [$params['auth']] : [];

                        $sentinel = new \RedisSentinel($host, $port, $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...$extra);
                    }

                    try {
                        if ($address = $sentinel->getMasterAddrByName($params['redis_sentinel'])) {
                            [$host, $port] = $address;
                        }
                    } catch (\RedisException $e) {
                    }
                } while (++$hostIndex < \count($hosts) && !$address);

                if (isset($params['redis_sentinel']) && !$address) {
                    throw new InvalidArgumentException(sprintf('Failed to retrieve master information from sentinel "%s".', $params['redis_sentinel']));
                }

                try {
                    $extra = [
                        'stream' => $params['ssl'] ?? null,
                    ];
                    $booleanStreamOptions = [
                        'allow_self_signed',
                        'capture_peer_cert',
                        'capture_peer_cert_chain',
                        'disable_compression',
                        'SNI_enabled',
                        'verify_peer',
                        'verify_peer_name',
                    ];

                    foreach ($extra['stream'] ?? [] as $streamOption => $value) {
                        if (\in_array($streamOption, $booleanStreamOptions, true) && \is_string($value)) {
                            $extra['stream'][$streamOption] = filter_var($value, \FILTER_VALIDATE_BOOL);
                        }
                    }

                    if (isset($params['auth'])) {
                        $extra['auth'] = $params['auth'];
                    }
                    @$redis->{$connect}($host, $port, $params['timeout'], (string) $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...\defined('Redis::SCAN_PREFIX') ? [$extra] : []);

                    set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
                    try {
                        $isConnected = $redis->isConnected();
                    } finally {
                        restore_error_handler();
                    }
                    if (!$isConnected) {
                        $error = preg_match('/^Redis::p?connect\(\): (.*)/', $error ?? $redis->getLastError() ?? '', $error) ? sprintf(' (%s)', $error[1]) : '';
                        throw new InvalidArgumentException('Redis connection failed: '.$error.'.');
                    }

                    if ((null !== $auth && !$redis->auth($auth))
                        // Due to a bug in phpredis we must always select the dbindex if persistent pooling is enabled
                        // @see https://github.com/phpredis/phpredis/issues/1920
                        // @see https://github.com/symfony/symfony/issues/51578
                        || (($params['dbindex'] || ('pconnect' === $connect && '0' !== \ini_get('redis.pconnect.pooling_enabled'))) && !$redis->select($params['dbindex']))
                    ) {
                        $e = preg_replace('/^ERR /', '', $redis->getLastError());
                        throw new InvalidArgumentException('Redis connection failed: '.$e.'.');
                    }

                    if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                        $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                    }
                } catch (\RedisException $e) {
                    throw new InvalidArgumentException('Redis connection failed: '.$e->getMessage());
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
                switch ($host['scheme']) {
                    case 'tcp': $hosts[$i] = $host['host'].':'.$host['port']; break;
                    case 'tls': $hosts[$i] = 'tls://'.$host['host'].':'.$host['port']; break;
                    default: $hosts[$i] = $host['path'];
                }
            }
            $params['lazy_connect'] = $params['lazy'] ?? true;
            $params['connect_timeout'] = $params['timeout'];

            try {
                $redis = new $class($hosts, $params);
            } catch (\RedisClusterException $e) {
                throw new InvalidArgumentException('Redis connection failed: '.$e->getMessage());
            }

            if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }
        } elseif (is_a($class, \RedisCluster::class, true)) {
            $initializer = static function () use ($class, $params, $hosts) {
                foreach ($hosts as $i => $host) {
                    switch ($host['scheme']) {
                        case 'tcp': $hosts[$i] = $host['host'].':'.$host['port']; break;
                        case 'tls': $hosts[$i] = 'tls://'.$host['host'].':'.$host['port']; break;
                        default: $hosts[$i] = $host['path'];
                    }
                }

                try {
                    $redis = new $class(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) $params['persistent'], $params['auth'] ?? '', ...\defined('Redis::SCAN_PREFIX') ? [$params['ssl'] ?? null] : []);
                } catch (\RedisClusterException $e) {
                    throw new InvalidArgumentException('Redis connection failed: '.$e->getMessage());
                }

                if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                    $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
                }
                switch ($params['failover']) {
                    case 'error': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR); break;
                    case 'distribute': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE); break;
                    case 'slaves': $redis->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE_SLAVES); break;
                }

                return $redis;
            };

            $redis = $params['lazy'] ? new RedisClusterProxy($initializer) : $initializer();
        } elseif (is_a($class, \Predis\ClientInterface::class, true)) {
            if ($params['redis_cluster']) {
                $params['cluster'] = 'redis';
            } elseif (isset($params['redis_sentinel'])) {
                $params['replication'] = 'sentinel';
                $params['service'] = $params['redis_sentinel'];
            }
            $params += ['parameters' => []];
            $params['parameters'] += [
                'persistent' => $params['persistent'],
                'timeout' => $params['timeout'],
                'read_write_timeout' => $params['read_timeout'],
                'tcp_nodelay' => true,
            ];
            if ($params['dbindex']) {
                $params['parameters']['database'] = $params['dbindex'];
            }
            if (null !== $auth) {
                $params['parameters']['password'] = $auth;
            }

            if (isset($params['ssl'])) {
                foreach ($hosts as $i => $host) {
                    if (!isset($host['ssl'])) {
                        $hosts[$i]['ssl'] = $params['ssl'];
                    }
                }
            }

            if (1 === \count($hosts) && !($params['redis_cluster'] || $params['redis_sentinel'])) {
                $hosts = $hosts[0];
            } elseif (\in_array($params['failover'], ['slaves', 'distribute'], true) && !isset($params['replication'])) {
                $params['replication'] = true;
                $hosts[0] += ['alias' => 'master'];
            }
            $params['exceptions'] = false;

            $redis = new $class($hosts, array_diff_key($params, self::$defaultConnectionOptions));
            if (isset($params['redis_sentinel'])) {
                $redis->getConnection()->setSentinelTimeout($params['timeout']);
            }
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Redis", "RedisArray", "RedisCluster" nor "Predis\ClientInterface".', $class));
        } else {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        return $redis;
    }

    protected function doFetch(array $ids)
    {
        if (!$ids) {
            return [];
        }

        $result = [];

        if ($this->redis instanceof \Predis\ClientInterface && $this->redis->getConnection() instanceof ClusterInterface) {
            $values = $this->pipeline(function () use ($ids) {
                foreach ($ids as $id) {
                    yield 'get' => [$id];
                }
            });
        } else {
            $values = $this->redis->mget($ids);

            if (!\is_array($values) || \count($values) !== \count($ids)) {
                return [];
            }

            $values = array_combine($ids, $values);
        }

        foreach ($values as $id => $v) {
            if ($v) {
                $result[$id] = $this->marshaller->unmarshall($v);
            }
        }

        return $result;
    }

    protected function doHave(string $id)
    {
        return (bool) $this->redis->exists($id);
    }

    protected function doClear(string $namespace)
    {
        if ($this->redis instanceof \Predis\ClientInterface) {
            $prefix = $this->redis->getOptions()->prefix ? $this->redis->getOptions()->prefix->getPrefix() : '';
            $prefixLen = \strlen($prefix ?? '');
        }

        $cleared = true;
        $hosts = $this->getHosts();
        $host = reset($hosts);
        if ($host instanceof \Predis\Client && $host->getConnection() instanceof ReplicationInterface) {
            // Predis supports info command only on the master in replication environments
            $hosts = [$host->getClientFor('master')];
        }

        foreach ($hosts as $host) {
            if (!isset($namespace[0])) {
                $cleared = $host->flushDb() && $cleared;
                continue;
            }

            $info = $host->info('Server');
            $info = !$info instanceof ErrorInterface ? $info['Server'] ?? $info : ['redis_version' => '2.0'];

            if (!$host instanceof \Predis\ClientInterface) {
                $prefix = \defined('Redis::SCAN_PREFIX') && (\Redis::SCAN_PREFIX & $host->getOption(\Redis::OPT_SCAN)) ? '' : $host->getOption(\Redis::OPT_PREFIX);
                $prefixLen = \strlen($host->getOption(\Redis::OPT_PREFIX) ?? '');
            }
            $pattern = $prefix.$namespace.'*';

            if (!version_compare($info['redis_version'], '2.8', '>=')) {
                // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
                // can hang your server when it is executed against large databases (millions of items).
                // Whenever you hit this scale, you should really consider upgrading to Redis 2.8 or above.
                $unlink = version_compare($info['redis_version'], '4.0', '>=') ? 'UNLINK' : 'DEL';
                $args = $this->redis instanceof \Predis\ClientInterface ? [0, $pattern] : [[$pattern], 0];
                $cleared = $host->eval("local keys=redis.call('KEYS',ARGV[1]) for i=1,#keys,5000 do redis.call('$unlink',unpack(keys,i,math.min(i+4999,#keys))) end return 1", $args[0], $args[1]) && $cleared;
                continue;
            }

            $cursor = null;
            do {
                $keys = $host instanceof \Predis\ClientInterface ? $host->scan($cursor, 'MATCH', $pattern, 'COUNT', 1000) : $host->scan($cursor, $pattern, 1000);
                if (isset($keys[1]) && \is_array($keys[1])) {
                    $cursor = $keys[0];
                    $keys = $keys[1];
                }
                if ($keys) {
                    if ($prefixLen) {
                        foreach ($keys as $i => $key) {
                            $keys[$i] = substr($key, $prefixLen);
                        }
                    }
                    $this->doDelete($keys);
                }
            } while ($cursor = (int) $cursor);
        }

        return $cleared;
    }

    protected function doDelete(array $ids)
    {
        if (!$ids) {
            return true;
        }

        if ($this->redis instanceof \Predis\ClientInterface && $this->redis->getConnection() instanceof ClusterInterface) {
            static $del;
            $del = $del ?? (class_exists(UNLINK::class) ? 'unlink' : 'del');

            $this->pipeline(function () use ($ids, $del) {
                foreach ($ids as $id) {
                    yield $del => [$id];
                }
            })->rewind();
        } else {
            static $unlink = true;

            if ($unlink) {
                try {
                    $unlink = false !== $this->redis->unlink($ids);
                } catch (\Throwable $e) {
                    $unlink = false;
                }
            }

            if (!$unlink) {
                $this->redis->del($ids);
            }
        }

        return true;
    }

    protected function doSave(array $values, int $lifetime)
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $results = $this->pipeline(function () use ($values, $lifetime) {
            foreach ($values as $id => $value) {
                if (0 >= $lifetime) {
                    yield 'set' => [$id, $value];
                } else {
                    yield 'setEx' => [$id, $lifetime, $value];
                }
            }
        });

        foreach ($results as $id => $result) {
            if (true !== $result && (!$result instanceof Status || Status::get('OK') !== $result)) {
                $failed[] = $id;
            }
        }

        return $failed;
    }

    private function pipeline(\Closure $generator, ?object $redis = null): \Generator
    {
        $ids = [];
        $redis = $redis ?? $this->redis;

        if ($redis instanceof RedisClusterProxy || $redis instanceof \RedisCluster || ($redis instanceof \Predis\ClientInterface && $redis->getConnection() instanceof RedisCluster)) {
            // phpredis & predis don't support pipelining with RedisCluster
            // see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#pipelining
            // see https://github.com/nrk/predis/issues/267#issuecomment-123781423
            $results = [];
            foreach ($generator() as $command => $args) {
                $results[] = $redis->{$command}(...$args);
                $ids[] = 'eval' === $command ? ($redis instanceof \Predis\ClientInterface ? $args[2] : $args[1][0]) : $args[0];
            }
        } elseif ($redis instanceof \Predis\ClientInterface) {
            $results = $redis->pipeline(static function ($redis) use ($generator, &$ids) {
                foreach ($generator() as $command => $args) {
                    $redis->{$command}(...$args);
                    $ids[] = 'eval' === $command ? $args[2] : $args[0];
                }
            });
        } elseif ($redis instanceof \RedisArray) {
            $connections = $results = $ids = [];
            foreach ($generator() as $command => $args) {
                $id = 'eval' === $command ? $args[1][0] : $args[0];
                if (!isset($connections[$h = $redis->_target($id)])) {
                    $connections[$h] = [$redis->_instance($h), -1];
                    $connections[$h][0]->multi(\Redis::PIPELINE);
                }
                $connections[$h][0]->{$command}(...$args);
                $results[] = [$h, ++$connections[$h][1]];
                $ids[] = $id;
            }
            foreach ($connections as $h => $c) {
                $connections[$h] = $c[0]->exec();
            }
            foreach ($results as $k => [$h, $c]) {
                $results[$k] = $connections[$h][$c];
            }
        } else {
            $redis->multi(\Redis::PIPELINE);
            foreach ($generator() as $command => $args) {
                $redis->{$command}(...$args);
                $ids[] = 'eval' === $command ? $args[1][0] : $args[0];
            }
            $results = $redis->exec();
        }

        if (!$redis instanceof \Predis\ClientInterface && 'eval' === $command && $redis->getLastError()) {
            $e = new \RedisException($redis->getLastError());
            $results = array_map(function ($v) use ($e) { return false === $v ? $e : $v; }, (array) $results);
        }

        if (\is_bool($results)) {
            return;
        }

        foreach ($ids as $k => $id) {
            yield $id => $results[$k];
        }
    }

    private function getHosts(): array
    {
        $hosts = [$this->redis];
        if ($this->redis instanceof \Predis\ClientInterface) {
            $connection = $this->redis->getConnection();
            if ($connection instanceof ClusterInterface && $connection instanceof \Traversable) {
                $hosts = [];
                foreach ($connection as $c) {
                    $hosts[] = new \Predis\Client($c);
                }
            }
        } elseif ($this->redis instanceof \RedisArray) {
            $hosts = [];
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof RedisClusterProxy || $this->redis instanceof \RedisCluster) {
            $hosts = [];
            foreach ($this->redis->_masters() as $host) {
                $hosts[] = new RedisClusterNodeProxy($host, $this->redis);
            }
        }

        return $hosts;
    }
}
