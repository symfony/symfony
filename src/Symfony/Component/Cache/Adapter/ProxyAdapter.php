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
class ProxyAdapter implements AdapterInterface
{
    private $pool;
    private $namespace;
    private $namespaceLen;
    private $createCacheItem;
    private $hits = 0;
    private $misses = 0;

    public function __construct(CacheItemPoolInterface $pool, $namespace = '', $defaultLifetime = 0)
    {
        $this->pool = $pool;
        $this->namespace = '' === $namespace ? '' : $this->getId($namespace);
        $this->namespaceLen = strlen($namespace);
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
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $f = $this->createCacheItem;
        $item = $this->pool->getItem($this->getId($key));
        if ($isHit = $item->isHit()) {
            ++$this->hits;
        } else {
            ++$this->misses;
        }

        return $f($key, $item->get(), $isHit);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        if ($this->namespaceLen) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->getId($key);
            }
        }

        return $this->generateItems($this->pool->getItems($keys));
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        return $this->pool->hasItem($this->getId($key));
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
        return $this->pool->deleteItem($this->getId($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        if ($this->namespaceLen) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->getId($key);
            }
        }

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
        $item = (array) $item;
        $expiry = $item[CacheItem::CAST_PREFIX.'expiry'];
        $poolItem = $this->pool->getItem($this->namespace.$item[CacheItem::CAST_PREFIX.'key']);
        $poolItem->set($item[CacheItem::CAST_PREFIX.'value']);
        $poolItem->expiresAt(null !== $expiry ? \DateTime::createFromFormat('U', $expiry) : null);

        return $this->pool->$method($poolItem);
    }

    private function generateItems($items)
    {
        $f = $this->createCacheItem;

        foreach ($items as $key => $item) {
            if ($isHit = $item->isHit()) {
                ++$this->hits;
            } else {
                ++$this->misses;
            }
            if ($this->namespaceLen) {
                $key = substr($key, $this->namespaceLen);
            }

            yield $key => $f($key, $item->get(), $isHit);
        }
    }

    /**
     * Returns the number of cache read hits.
     *
     * @return int
     */
    public function getHits()
    {
        return $this->hits;
    }

    /**
     * Returns the number of cache read misses.
     *
     * @return int
     */
    public function getMisses()
    {
        return $this->misses;
    }

    private function getId($key)
    {
        return $this->namespace.CacheItem::validateKey($key);
    }
}
