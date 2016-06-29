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

use Predis\Connection\Factory;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RedisAdapter extends AbstractAdapter
{
    use RedisAdapterTrait;

    private static $defaultConnectionOptions = array(
        'class' => null,
        'persistent' => 0,
        'timeout' => 0,
        'read_timeout' => 0,
        'retry_interval' => 0,
    );

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    public function __construct($redisClient, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->setRedis($redisClient, $namespace);
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
     * @throws InvalidArgumentException When the DSN is invalid.
     *
     * @return \Redis|\Predis\Client According to the "class" option
     */
    public static function createConnection($dsn, array $options = array())
    {
        if (0 !== strpos($dsn, 'redis://')) {
            throw new InvalidArgumentException(sprintf('Invalid Redis DSN: %s does not start with "redis://"', $dsn));
        }
        $params = preg_replace_callback('#^redis://(?:([^@]*)@)?#', function ($m) use (&$auth) {
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
            $connect = empty($params['persistent']) ? 'connect' : 'pconnect';
            $redis = new $class();
            @$redis->{$connect}($params['host'], $params['port'], $params['timeout'], null, $params['retry_interval']);

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
            $params['scheme'] = isset($params['host']) ? 'tcp' : 'unix';
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
        $result = array();

        if ($ids) {
            $values = $this->redis->mGet($ids);
            $index = 0;
            foreach ($ids as $id) {
                if ($value = $values[$index++]) {
                    $result[$id] = unserialize($value);
                }
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

        if (0 >= $lifetime) {
            $this->redis->mSet($serialized);

            return $failed;
        }

        $this->pipeline(function ($pipe) use (&$serialized, $lifetime) {
            foreach ($serialized as $id => $value) {
                $pipe('setEx', $id, array($lifetime, $value));
            }
        });

        return $failed;
    }
}
