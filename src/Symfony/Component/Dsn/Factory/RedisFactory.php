<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Factory;

use Predis\Connection\Factory;
use Symfony\Component\Dsn\Exception\InvalidArgumentException;

/**
 * Factory for Redis connections.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RedisFactory
{
    private static $defaultConnectionOptions = array(
        'class' => null,
        'persistent' => 0,
        'persistent_id' => null,
        'timeout' => 30,
        'read_timeout' => 0,
        'retry_interval' => 0,
    );

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
    public static function create($dsn, array $options = array())
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
        $class = null === $params['class'] ? (extension_loaded('redis') ? \Redis::class : \Predis\Client::class) : $params['class'];

        if (is_a($class, \Redis::class, true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();
            @$redis->{$connect}($params['host'], $params['port'], $params['timeout'], $params['persistent_id'], $params['retry_interval']);

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
}
