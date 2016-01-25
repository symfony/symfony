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
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractAdapter implements CacheItemPoolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $namespace;
    private $deferred = array();
    private $createCacheItem;
    private $mergeByLifetime;

    protected function __construct($namespace = '', $defaultLifetime = 0)
    {
        $this->namespace = $namespace;
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
     * @return array|\Traversable The corresponding values found in the cache.
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
     * @param string The prefix used for all identifiers managed by this pool.
     *
     * @return bool True if the pool was successfully cleared, false otherwise.
     */
    abstract protected function doClear($namespace);

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
        if ($this->deferred) {
            $this->commit();
        }
        $id = $this->getId($key);

        $f = $this->createCacheItem;
        $isHit = false;
        $value = null;

        try {
            foreach ($this->doFetch(array($id)) as $value) {
                $isHit = true;
            }
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to fetch key "{key}"', array('key' => $key, 'exception' => $e));
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
        $ids = array();

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
        }
        try {
            $items = $this->doFetch($ids);
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to fetch requested items', array('keys' => $keys, 'exception' => $e));
            $items = array();
        }
        $ids = array_flip($ids);

        return $this->generateItems($items, $ids);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $id = $this->getId($key);

        if (isset($this->deferred[$key])) {
            $item = (array) $this->deferred[$key];
            try {
                $e = null;
                $value = $item[CacheItem::CAST_PREFIX.'value'];
                $ok = $this->doSave(array($key => $value), $item[CacheItem::CAST_PREFIX.'lifetime']);
                unset($this->deferred[$key]);

                if (true === $ok || array() === $ok) {
                    return true;
                }
            } catch (\Exception $e) {
            }
            $type = is_object($value) ? get_class($value) : gettype($value);
            CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', array('key' => $key, 'type' => $type, 'exception' => $e));
        }

        try {
            return $this->doHave($id);
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to check if key "{key}" is cached', array('key' => $key, 'exception' => $e));

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();

        try {
            return $this->doClear($this->namespace);
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to clear the cache', array('exception' => $e));

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        return $this->deleteItems(array($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $ids = array();

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->doDelete($ids)) {
                return true;
            }
        } catch (\Exception $e) {
        }

        $ok = true;

        // When bulk-save failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->doDelete(array($id))) {
                    continue;
                }
            } catch (\Exception $e) {
            }
            CacheItem::log($this->logger, 'Failed to delete key "{key}"', array('key' => $key, 'exception' => $e));
            $ok = false;
        }

        return $ok;
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
        try {
            $item = clone $item;
        } catch (\Exception $e) {
            $value = $item->get();
            $type = is_object($value) ? get_class($value) : gettype($value);
            CacheItem::log($this->logger, 'Failed to clone key "{key}" ({type})', array('key' => $key, 'type' => $type, 'exception' => $e));
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $f = $this->mergeByLifetime;
        $ko = array();

        foreach ($f($this->deferred, $this->namespace) as $lifetime => $values) {
            try {
                if (true === $ok = $this->doSave($values, $lifetime)) {
                    continue;
                }
            } catch (\Exception $e) {
                $ok = false;
            }
            if (false === $ok) {
                $ok = array_keys($values);
            }
            foreach ($ok as $id) {
                $ko[$lifetime][] = array($id => $values[$id]);
            }
        }

        $this->deferred = array();
        $ok = true;

        // When bulk-save failed, retry each item individually
        foreach ($ko as $lifetime => $values) {
            foreach ($values as $v) {
                try {
                    $e = $this->doSave($v, $lifetime);
                } catch (\Exception $e) {
                }
                if (true !== $e && array() !== $e) {
                    $ok = false;
                    foreach ($v as $key => $value) {
                        $type = is_object($value) ? get_class($value) : gettype($value);
                        CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', array('key' => $key, 'type' => $type, 'exception' => $e instanceof \Exception ? $e : null));
                    }
                }
            }
        }

        return $ok;
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

    private function generateItems($items, &$keys)
    {
        $f = $this->createCacheItem;

        foreach ($items as $id => $value) {
            yield $keys[$id] => $f($keys[$id], $value, true);
            unset($keys[$id]);
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }
}
