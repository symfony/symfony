<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CounterInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SimpleCache implements CacheInterface, CounterInterface
{
    private $pool;
    private $createCacheItem;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;

        if ($pool instanceof Adapter\AdapterInterface) {
            $this->createCacheItem = \Closure::bind(
                function ($key, $value) {
                    CacheItem::validateKey($key);
                    $item = new CacheItem();
                    $item->key = $key;
                    $item->value = $value;

                    return $item;
                },
                null,
                CacheItem::class
            );
        } else {
            $this->createCacheItem = function ($key, $value) {
                return $this->pool->getItem($key)->set($value);
            };
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $f = $this->createCacheItem;
        $item = $f($key, $value);
        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }

        return $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys)
    {
        if ($key instanceof \Traversable) {
            $key = iterator_to_array($key);
        }
        $values = array();

        foreach ($this->pool->getItems($keys) as $key => $item) {
            $values[$key] = $item->get();
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($items, $ttl = null)
    {
        $f = $this->createCacheItem;
        $ok = true;

        foreach ($items as $key => $value) {
            $item = $f($key, $value);
            if (null !== $ttl) {
                $item->expiresAfter($ttl);
            }
            $ok = $this->pool->saveDeferred($item) && $ok;
        }

        return $this->pool->commit() && $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if ($key instanceof \Traversable) {
            $key = iterator_to_array($key);
        }

        return $this->pool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->pool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * This implementation is not atomic unless the underlying pool implements PSR-16's CounterInterface.
     */
    public function increment($key, $step = 1)
    {
        if ($this->pool instanceof CounterInterface) {
            return $this->pool->increment($key, $step);
        }
        if (!is_numeric($step)) {
            return false;
        }
        $step = (int) $step;

        $item = $this->pool->getItem($key);
        if (is_numeric($value = $item->get())) {
            $step += $value;
        }
        $item->set($step);

        return $this->pool->save($item) ? $step : false;
    }

    /**
     * {@inheritdoc}
     *
     * This method is atomic only if the underlying pool implements PSR-16's CounterInterface.
     */
    public function decrement($key, $step = 1)
    {
        return is_numeric($step) ? $this->increment($key, -$step) : false;
    }
}
