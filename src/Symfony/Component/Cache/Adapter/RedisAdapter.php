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

use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 */
class RedisAdapter extends AbstractAdapter
{
    private static $defaultConnectionOptions = array(
        'class' => \Redis::class,
        'persistent' => 0,
        'timeout' => 0,
        'read_timeout' => 0,
        'retry_interval' => 0,
    );
    private $redis;

    public function __construct(\Redis $redisClient, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
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
     * @throws InvalidArgumentException When the DSN is invalid.
     *
     * @return \Redis
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
        $class = $params['class'];

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
        } elseif (class_exists($class, false)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a subclass of "Redis"', $class));
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
        return $this->redis->exists($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
        // can hang your server when it is executed against large databases (millions of items).
        // Whenever you hit this scale, it is advised to deploy one Redis database per cache pool
        // instead of using namespaces, so that FLUSHDB is used instead.
        $lua = "local keys=redis.call('KEYS',ARGV[1]..'*') for i=1,#keys,5000 do redis.call('DEL',unpack(keys,i,math.min(i+4999,#keys))) end";

        if (!isset($namespace[0])) {
            $this->redis->flushDb();
        } else {
            $this->redis->eval($lua, array($namespace), 0);
        }

        return true;
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
        if ($lifetime > 0) {
            $this->redis->multi(\Redis::PIPELINE);
            foreach ($serialized as $id => $value) {
                $this->redis->setEx($id, $lifetime, $value);
            }
            if (!$this->redis->exec()) {
                return false;
            }
        } elseif (!$this->redis->mSet($serialized)) {
            return false;
        }

        return $failed;
    }
}
