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

use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TagAwareRedisAdapter extends AbstractTagAwareAdapter
{
    use RedisAdapterTrait;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    public function __construct($redisClient, $namespace = '', $defaultLifetime = 0, AdapterInterface $adapter = null)
    {
        parent::__construct($adapter ?: new RedisAdapter($redisClient, $namespace, $defaultLifetime), $defaultLifetime);
        $this->setRedis($redisClient, $namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $ok = $this->doClear($this->namespace);

        return parent::clear() && $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags($tags)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $this->pipeline(function ($pipe) use ($tags) {
            foreach ($tags as $tag) {
                CacheItem::validateKey($tag);
                $pipe('incr', $this->namespace.'tag:'.$tag);
            }
        });

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterInvalidatedKeys(array &$keys)
    {
        $tags = $invalids = array();

        foreach ($keys as $i => $key) {
            CacheItem::validateKey($key);

            foreach ($this->redis->hGetAll($this->namespace.$key.':tags') as $tag => $version) {
                $tags[$this->namespace.'tag:'.$tag][$version][$i] = $key;
            }
        }
        if ($tags) {
            $j = 0;
            $versions = $this->redis->mGet(array_keys($tags));

            foreach ($tags as $tag => $version) {
                $version = $versions[$j++];
                unset($tags[$tag][(int) $version]);

                foreach ($tags[$tag] as $version) {
                    foreach ($version as $i => $key) {
                        $invalids[] = $key;
                        unset($keys[$i]);
                    }
                }
            }
        }

        return $invalids;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSaveTags(array $tagsByKey)
    {
        $tagVersions = array();

        foreach ($tagsByKey as $key => $tags) {
            foreach ($tags as $tag) {
                $tagVersions[$tag] = $this->namespace.'tag:'.$tag;
            }
        }

        if ($tagVersions) {
            $tagVersions = array_combine(array_keys($tagVersions), $this->redis->mGet($tagVersions));
            $tagVersions = array_map('intval', $tagVersions);
        }

        $this->pipeline(function ($pipe) use ($tagsByKey, $tagVersions) {
            foreach ($tagsByKey as $key => $tags) {
                $pipe('del', $this->namespace.$key.':tags');
                if ($tags) {
                    foreach (array_intersect_key($tagVersions, $tags) as $tag => $version) {
                        $pipe('hSet', $this->namespace.$key.':tags', array($tag, $version));
                    }
                }
            }
        });

        return true;
    }
}
