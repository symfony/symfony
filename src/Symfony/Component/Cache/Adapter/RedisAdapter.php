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
    private $redis;

    public function __construct(\Redis $redisConnection, $namespace = '', $defaultLifetime = 0)
    {
        $this->redis = $redisConnection;

        if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }

        parent::__construct($namespace, $defaultLifetime);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = $this->redis->mget($ids);
        $index = 0;
        $result = [];

        foreach ($ids as $id) {
            if (false !== $value = $values[$index++]) {
                $result[$id] = unserialize($value);
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
        if (!isset($namespace[0])) {
            $this->redis->flushDB();
        } else {
            // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
            // can hang your server when it is executed against large databases (millions of items).
            // Whenever you hit this scale, it is advised to deploy one Redis database per cache pool
            // instead of using namespaces, so that the above FLUSHDB is used instead.
            $this->redis->eval(sprintf("local keys=redis.call('KEYS','%s*') for i=1,#keys,5000 do redis.call('DEL',unpack(keys,i,math.min(i+4999,#keys))) end", $namespace));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $this->redis->del($ids);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $failed = array();

        foreach ($values as $id => $v) {
            try {
                $values[$id] = serialize($v);
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }

        if (!$this->redis->mSet($values)) {
            return false;
        }

        if ($lifetime >= 1) {
            foreach ($values as $id => $v) {
                $this->redis->expire($id, $lifetime);
            }
        }

        return $failed;
    }
}
