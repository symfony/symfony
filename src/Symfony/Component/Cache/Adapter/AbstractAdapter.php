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
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @var AdapterInterface
     */
    private $decorated;

    /**
     * @var array
     */
    private $deferred = array();

    /**
     * @var \Closure
     */
    private $createCacheItem;

    /**
     * @var \Closure
     */
    private $mergeByLifetime;

    protected function __construct($namespace = '', $defaultLifetime = 0, AdapterInterface $decorated = null)
    {
        $this->namespace = $namespace;
        $this->decorated = $decorated;
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, $isHit) use ($defaultLifetime) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                $item->defaultLifetime = $defaultLifetime;

                return $item;
            },
            $this,
            CacheItem::class
        );
        $this->mergeByLifetime = \Closure::bind(
            function ($deferred, $namespace) {
                $byLifetime = array();

                foreach ($deferred as $key => $item) {
                    if (0 <= $item->lifetime) {
                        $byLifetime[(int) $item->lifetime][$namespace.$key] = $item->value;
                    }
                }

                return $byLifetime;
            },
            $this,
            CacheItem::class
        );
    }

    /**
     * Fetches several cache items.
     *
     * @param array $ids The cache identifiers to fetch.
     *
     * @return array The corresponding values found in the cache.
     */
    abstract protected function doFetch(array $ids);

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $id The identifier for which to check existence.
     *
     * @return bool True if item exists in the cache, false otherwise.
     */
    abstract protected function doHave($id);

    /**
     * Deletes all items in the pool.
     *
     * @return bool True if the pool was successfully cleared, false otherwise.
     */
    abstract protected function doClear();

    /**
     * Removes multiple items from the pool.
     *
     * @param array $ids An array of identifiers that should be removed from the pool.
     *
     * @return bool True if the items were successfully removed, false otherwise.
     */
    abstract protected function doDelete(array $ids);

    /**
     * Persists several cache items immediately.
     *
     * @param array $values   The values to cache, indexed by their cache identifier.
     * @param int   $lifetime The lifetime of the cached values, 0 for persisting until manual cleaning.
     *
     * @return array|bool The identifiers that failed to be cached or a boolean stating if caching succeeded or not.
     */
    abstract protected function doSave(array $values, $lifetime);

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $id = $this->getId($key);
        if ($this->deferred) {
            $this->commit();
        }
        if (isset($this->deferred[$key])) {
            return $this->deferred[$key];
        }

        $f = $this->createCacheItem;
        $isHit = false;
        $value = null;

        foreach ($this->doFetch(array($id)) as $value) {
            $isHit = true;
        }

        if (!$isHit && $this->decorated) {
            return $this->decorated->getItem($key);
        }

        return $f($key, $value, $isHit);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        if ($this->deferred) {
            $this->commit();
        }
        $f = $this->createCacheItem;
        $ids = array();
        $items = array();

        foreach ($keys as $key) {
            $id = $this->getId($key);

            if (isset($this->deferred[$key])) {
                $items[$key] = $this->deferred[$key];
            } else {
                $ids[$key] = $id;
            }
        }

        $values = $this->doFetch($ids);

        foreach ($ids as $key => $id) {
            $isHit = isset($values[$id]);

            if (!$isHit && $this->decorated) {
                $items[$key] = $this->getItem($key);

                continue;
            }

            $items[$key] = $f($key, $isHit ? $values[$id] : null, $isHit);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }

        $got = $this->doHave($this->getId($key));

        if ($got || !$this->decorated) {
            return $got;
        }

        return $this->decorated->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();
        $cleared = $this->decorated ? $this->decorated->clear() : true;

        return $this->doClear() && $cleared;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $deleted = $this->decorated ? $this->decorated->deleteItem($key) : true;

        return $this->deleteItems(array($key)) && $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $ids = array();

        foreach ($keys as $key) {
            $ids[] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        $deleted = $this->decorated ? $this->decorated->deleteItems($keys) : true;

        return $this->doDelete($ids) && $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $key = $item->getKey();
        $this->deferred[$key] = $item;
        $this->commit();

        $saved = $this->decorated ? $this->decorated->save($item) : true;

        return !isset($this->deferred[$key]) && $saved;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        try {
            $item = clone $item;
        } catch (\Error $e) {
        } catch (\Exception $e) {
        }
        if (isset($e)) {
            @trigger_error($e->__toString());

            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return $this->decorated ? $this->decorated->saveDeferred($item) : true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $f = $this->mergeByLifetime;
        $ko = array();
        $namespaceLen = strlen($this->namespace);

        foreach ($f($this->deferred, $this->namespace) as $lifetime => $values) {
            if (true === $ok = $this->doSave($values, $lifetime)) {
                continue;
            }
            if (false === $ok) {
                $ok = array_keys($values);
            }
            foreach ($ok as $failedId) {
                $key = substr($failedId, $namespaceLen);
                $ko[$key] = $this->deferred[$key];
            }
        }

        $committed = $this->decorated ? $this->decorated->commit() : true;

        return !($this->deferred = $ko) && $committed;
    }

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    private function getId($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (!isset($key[0])) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }
        if (isset($key[strcspn($key, '{}()/\@:')])) {
            throw new InvalidArgumentException('Cache key contains reserved characters {}()/\@:');
        }

        return $this->namespace.$key;
    }
}
