<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Factory;

use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Dsn\Configuration\Dsn;
use Symfony\Component\Dsn\Configuration\Path;
use Symfony\Component\Dsn\Configuration\Url;
use Symfony\Component\Dsn\ConnectionFactoryInterface;
use Symfony\Component\Dsn\DsnParser;
use Symfony\Component\Dsn\Exception\FailedToConnectException;
use Symfony\Component\Dsn\Exception\FunctionNotSupportedException;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;
use Symfony\Component\Dsn\Exception\InvalidDsnException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class RedisFactory implements ConnectionFactoryInterface
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
    ];

    /**
     * Example DSN strings.
     *
     * - redis://localhost:6379?timeout=10
     * - redis(redis://127.0.0.1)?persistent_id=foobar
     * - redis(redis://127.0.0.1/20 redis://127.0.0.2?timeout=10)?lazy=1
     */
    public static function create(string $dsnString): object
    {
        $rootDsn = DsnParser::parseFunc($dsnString);
        if ('dsn' !== $rootDsn->getName() && 'redis' !== $rootDsn->getName()) {
            throw new FunctionNotSupportedException($dsnString, $rootDsn->getName());
        }
        $params = $rootDsn->getParameters() + self::$defaultConnectionOptions;
        $auth = null;

        $hosts = [];
        foreach ($rootDsn->getArguments() as $dsn) {
            if (!$dsn instanceof Dsn) {
                throw new InvalidArgumentException('Only one DSN function is allowed.');
            }
            if ('redis' !== $dsn->getScheme() && 'rediss' !== $dsn->getScheme()) {
                throw new InvalidDsnException($dsn->__toString(), 'Invalid Redis DSN: The scheme must be "redis:" or "rediss".');
            }

            $auth = $dsn->getPassword() ?? $dsn->getUser();
            $path = $dsn->getPath();
            $params['dbindex'] = 0;
            if (null !== $path && preg_match('#/(\d+)$#', $path, $m)) {
                $params['dbindex'] = (int) $m[1];
                $path = substr($path, 0, -\strlen($m[0]));
            }

            if ($dsn instanceof Url) {
                array_unshift($hosts, ['scheme' => 'tcp', 'host' => $dsn->getHost(), 'port' => $dsn->getPort() ?? 6379]);
            } elseif ($dsn instanceof Path) {
                array_unshift($hosts, ['scheme' => 'unix', 'path' => $path]);
            }

            foreach ($dsn->getParameter('host', []) as $host => $parameters) {
                if (\is_string($parameters)) {
                    parse_str($parameters, $parameters);
                }
                if (false === $i = strrpos($host, ':')) {
                    $hosts[$host] = ['scheme' => 'tcp', 'host' => $host, 'port' => 6379] + $parameters;
                } elseif ($port = (int) substr($host, 1 + $i)) {
                    $hosts[$host] = ['scheme' => 'tcp', 'host' => substr($host, 0, $i), 'port' => $port] + $parameters;
                } else {
                    $hosts[$host] = ['scheme' => 'unix', 'path' => substr($host, 0, $i)] + $parameters;
                }
            }
            $hosts = array_values($hosts);
            $params = $dsn->getParameters() + $params;
        }

        if (empty($hosts)) {
            throw new InvalidDsnException($dsnString, 'Invalid Redis DSN: The DSN does not contain any hosts.');
        }

        if (isset($params['redis_sentinel']) && !class_exists(\Predis\Client::class)) {
            throw new InvalidArgumentException(sprintf('Redis Sentinel support requires the "predis/predis" package: "%s".', $dsn));
        }

        if (null === $params['class'] && !isset($params['redis_sentinel']) && \extension_loaded('redis')) {
            $class = $params['redis_cluster'] ? \RedisCluster::class : (1 < \count($hosts) ? \RedisArray::class : \Redis::class);
        } else {
            $class = null === $params['class'] ? \Predis\Client::class : $params['class'];
        }

        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();

            $initializer = function ($redis) use ($connect, $params, $dsn, $auth, $hosts) {
                try {
                    @$redis->{$connect}($hosts[0]['host'] ?? $hosts[0]['path'], $hosts[0]['port'] ?? null, (float) $params['timeout'], (string) $params['persistent_id'], $params['retry_interval']);
                } catch (\RedisException $e) {
                    throw new FailedToConnectException(sprintf('Redis connection "%s" failed: ', $dsn).$e->getMessage(), 0, $e);
                }

                set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
                $isConnected = $redis->isConnected();
                restore_error_handler();
                if (!$isConnected) {
                    $error = preg_match('/^Redis::p?connect\(\): (.*)/', $error, $error) ? sprintf(' (%s)', $error[1]) : '';
                    throw new FailedToConnectException(sprintf('Redis connection "%s" failed: ', $dsn).$error.'.');
                }

                if ((null !== $auth && !$redis->auth($auth))
                    || ($params['dbindex'] && !$redis->select($params['dbindex']))
                    || ($params['read_timeout'] && !$redis->setOption(\Redis::OPT_READ_TIMEOUT, $params['read_timeout']))
                ) {
                    $e = preg_replace('/^ERR /', '', $redis->getLastError());
                    throw new FailedToConnectException(sprintf('Redis connection "%s" failed: ', $dsn).$e.'.');
                }

                if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                    $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
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
                throw new InvalidArgumentException(sprintf('Redis connection "%s" failed: ', $dsn).$e->getMessage());
            }

            if (0 < $params['tcp_keepalive'] && \defined('Redis::OPT_TCP_KEEPALIVE')) {
                $redis->setOption(\Redis::OPT_TCP_KEEPALIVE, $params['tcp_keepalive']);
            }
        } elseif (is_a($class, \RedisCluster::class, true)) {
            $initializer = function () use ($class, $params, $dsn, $hosts) {
                foreach ($hosts as $i => $host) {
                    $hosts[$i] = 'tcp' === $host['scheme'] ? $host['host'].':'.$host['port'] : $host['path'];
                }

                try {
                    $redis = new $class(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) $params['persistent']);
                } catch (\RedisClusterException $e) {
                    throw new InvalidArgumentException(sprintf('Redis connection "%s" failed: ', $dsn).$e->getMessage());
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
                if (isset($params['redis_sentinel'])) {
                    throw new InvalidArgumentException(sprintf('Cannot use both "redis_cluster" and "redis_sentinel" at the same time: "%s".', $dsn));
                }
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

    public static function supports(string $dsn): bool
    {
        return 0 === strpos($dsn, 'redis:') || 0 === strpos($dsn, 'rediss:');
    }
}
