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
use Predis\Connection\Aggregate\PredisCluster;
use Predis\Connection\Aggregate\RedisCluster;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RedisAdapter extends AbstractTagsInvalidatingAdapter
{
    private static $defaultConnectionOptions = array(
        'class' => null,
        'persistent' => 0,
        'timeout' => 0,
        'read_timeout' => 0,
        'retry_interval' => 0,
    );
    private $redis;
    private $namespace;
    private $namespaceLen;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    public function __construct($redisClient, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if (!$redisClient instanceof \Redis && !$redisClient instanceof \RedisArray && !$redisClient instanceof \RedisCluster && !$redisClient instanceof \Predis\Client) {
            throw new InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, is_object($redisClient) ? get_class($redisClient) : gettype($redisClient)));
        }
        $this->redis = $redisClient;
        $this->namespace = $namespace;
        $this->namespaceLen = strlen($namespace);
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
     * @return \Redis|\Predis\Client According to the "class" option.
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
    public function invalidateTags($tags)
    {
        $tags = CacheItem::normalizeTags($tags);

        foreach ($tags as $tag) {
            $this->doInvalidateTag($this->namespace.$tag);
        }

        return true;
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
    protected function doSaveWithTags(array $values, $lifetime, array $tags)
    {
        $serialized = array();
        $failed = array();
        $oldTagsById = $oldKeysByTag = $newKeys = array();

        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);

                $key = substr($id, $this->namespaceLen);
                foreach ($this->redis->sMembers($id.':tag') as $tag) {
                    if (!isset($tags[$id][$tag])) {
                        $oldTagsById[$id][] = $tag;
                        $oldKeysByTag[$tag][] = $key;
                    }
                }
                foreach ($tags[$id] as $tag) {
                    $newKeys[$tag][] = $key;
                }
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        if (!$serialized) {
            return $failed;
        }

        if (0 >= $lifetime && !$newKeys && !$oldTagsById) {
            $this->redis->mSet($serialized);

            return $failed;
        }

        $this->pipeline(function ($pipe) use (&$serialized, $lifetime, &$oldTagsById, &$oldKeysByTag, &$tags, &$newKeys) {
            foreach ($oldKeysByTag as $tag => $keys) {
                $pipe('sRem', $this->namespace.$tag, $keys);
            }
            foreach ($oldTagsById as $id => $keys) {
                $pipe('sRem', $id.':tag', $keys);
            }

            foreach ($serialized as $id => $value) {
                if (0 < $lifetime) {
                    $pipe('setEx', $id, array($lifetime, $value));
                } else {
                    $pipe('set', $id, array($value));
                }
                if ($tags[$id]) {
                    $pipe('sAdd', $id.':tag', $tags[$id]);
                }
            }

            foreach ($newKeys as $tag => $keys) {
                $pipe('sAdd', $this->namespace.$tag, $keys);

                while (0 < $r = strrpos($tag, '/')) {
                    $parent = substr($tag, 0, $r);
                    $pipe('sAdd', $this->namespace.$parent.':child', array($tag));
                    $tag = $parent;
                }
            }
        });

        return $failed;
    }

    private function doInvalidateTag($tag)
    {
        foreach ($this->sScan($tag.':child') as $children) {
            $this->execute('sRem', $tag.':child', $children);
            foreach ($children as $child) {
                $this->doInvalidateTag($this->namespace.$child);
            }
        }

        $count = 0;

        foreach ($this->sScan($tag) as $ids) {
            $count += count($ids);
            $this->execute('sRem', $tag, $ids);
            foreach ($ids as $k => $id) {
                $ids[$k] = $this->namespace.$id;
            }
            $this->redis->del($ids);
        }

        CacheItem::log($this->logger, 'Invalidating {count} items tagged as "{tag}"', array('tag' => $tag, 'count' => $count));
    }

    private function sScan($id)
    {
        $redis = $this->redis instanceof \RedisArray ? $this->redis->_instance($this->redis->_target($id)) : $this->redis;

        if (method_exists($redis, 'sScan')) {
            try {
                $cursor = null;
                do {
                    $ids = $redis->sScan($id, $cursor);

                    if (isset($ids[1]) && is_array($ids[1])) {
                        $cursor = $ids[0];
                        $ids = $ids[1];
                    }
                    if ($ids) {
                        yield $ids;
                    }
                } while ($cursor);

                return;
            } catch (\Exception $e) {
            }
        }
        if ($ids = $redis->sMembers($id)) {
            yield $ids;
        }
    }

    private function execute($command, $id, array $args, $redis = null)
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
                $callback(function ($command, $id, $args) use (&$connections) {
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
