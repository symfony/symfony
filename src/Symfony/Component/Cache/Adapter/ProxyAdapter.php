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
use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ProxyAdapter implements CacheItemPoolInterface
{
    private $pool;
    private $createCacheItem;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            $this,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $f = $this->createCacheItem;
        $item = $this->pool->getItem($key);

        return $f($key, $item->get(), $item->isHit());
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        $f = $this->createCacheItem;
        $items = array();

        foreach ($this->pool->getItems($keys) as $key => $item) {
            $items[$key] = $f($key, $item->get(), $item->isHit());
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        return $this->pool->hasItem($key);
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
    public function deleteItem($key)
    {
        return $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        return $this->pool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        return $this->doSave($item, __FUNCTION__);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        return $this->doSave($item, __FUNCTION__);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->pool->commit();
    }

    private function doSave(CacheItemInterface $item, $method)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        static $prefix = "\0Symfony\Component\Cache\CacheItem\0";
        $item = (array) $item;
        $poolItem = $this->pool->getItem($item[$prefix.'key']);
        $poolItem->set($item[$prefix.'value']);
        $poolItem->expiresAfter($item[$prefix.'lifetime']);

        return $this->pool->$method($poolItem);
    }
}
