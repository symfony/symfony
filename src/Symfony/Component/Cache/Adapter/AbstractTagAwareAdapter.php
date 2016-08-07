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

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractTagAwareAdapter implements TagAwareAdapterInterface
{
    private $adapter;
    private $deferred = array();
    private $createCacheItem;
    private $getTagsByKey;

    /**
     * Removes tag-invalidated keys and returns the removed ones.
     *
     * @param array &$keys The keys to filter
     *
     * @return array The keys removed from $keys
     */
    abstract protected function filterInvalidatedKeys(array &$keys);

    /**
     * Persists tags for cache keys.
     *
     * @param array $tags The tags for each cache keys as index
     *
     * @return bool True on success
     */
    abstract protected function doSaveTags(array $tags);

    public function __construct(AdapterInterface $adapter, $defaultLifetime)
    {
        $this->adapter = $adapter;
        $this->createCacheItem = \Closure::bind(
            function ($key) use ($defaultLifetime) {
                $item = new CacheItem();
                $item->key = $key;
                $item->isHit = false;
                $item->defaultLifetime = $defaultLifetime;

                return $item;
            },
            null,
            CacheItem::class
        );
        $this->getTagsByKey = \Closure::bind(
            function ($deferred) {
                $tagsByKey = array();
                foreach ($deferred as $key => $item) {
                    $tagsByKey[$key] = $item->tags;
                }

                return $tagsByKey;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
        if (!$this->adapter->hasItem($key)) {
            return false;
        }
        $keys = array($key);

        return !$this->filterInvalidatedKeys($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
        $keys = array($key);

        if ($keys = $this->filterInvalidatedKeys($keys)) {
            foreach ($this->generateItems(array(), $keys) as $item) {
                return $item;
            }
        }

        return $this->adapter->getItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        if ($this->deferred) {
            $this->commit();
        }
        $invalids = $this->filterInvalidatedKeys($keys);
        $items = $this->adapter->getItems($keys);

        return $this->generateItems($items, $invalids);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();

        return $this->adapter->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        return $this->adapter->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        return $this->adapter->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        if ($this->deferred) {
            $this->commit();
        }
        $this->deferred[$item->getKey()] = $item;

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $ok = true;

        if ($this->deferred) {
            foreach ($this->deferred as $key => $item) {
                if (!$this->adapter->saveDeferred($item)) {
                    unset($this->deferred[$key]);
                    $ok = false;
                }
            }
            $f = $this->getTagsByKey;
            $ok = $this->doSaveTags($f($this->deferred)) && $ok;
            $this->deferred = array();
        }

        return $this->adapter->commit() && $ok;
    }

    public function __destruct()
    {
        $this->commit();
    }

    private function generateItems($items, $invalids)
    {
        foreach ($items as $key => $item) {
            yield $key => $item;
        }

        $f = $this->createCacheItem;

        foreach ($invalids as $key) {
            yield $key => $f($key);
        }
    }
}
