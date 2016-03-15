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

/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 */
class RedisAdapter extends AbstractAdapter
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @param \Redis $redisConnection
     * @param string $namespace
     * @param int    $defaultLifetime
     */
    public function __construct(\Redis $redisConnection, $namespace = '', $defaultLifetime = 0)
    {
        $this->redis = $redisConnection;

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
            $value = $values[$index++];

            if (false === $value) {
                continue;
            }

            $result[$id] = unserialize($value);
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
    protected function doClear()
    {
        return $this->redis->flushDB();
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
        $failed = [];
        foreach ($values as $key => $value) {
            $value = serialize($value);

            if ($lifetime < 1) {
                $response = $this->redis->set($key, $value);
            } else {
                $response = $this->redis->setex($key, $lifetime, $value);
            }

            if (false === $response) {
                $failed[] = $key;
            }
        }

        return count($failed) > 0 ? $failed : true;
    }
}
