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
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ProxyTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ProxyAdapter implements AdapterInterface, PruneableInterface, ResettableInterface
{
    use ProxyTrait;

    private $namespace;
    private $namespaceLen;
    private $createCacheItem;
    private $poolHash;

    public function __construct(CacheItemPoolInterface $pool, string $namespace = '', int $defaultLifetime = 0)
    {
        $this->pool = $pool;
        $this->poolHash = $poolHash = spl_object_hash($pool);
        $this->namespace = '' === $namespace ? '' : CacheItem::validateKey($namespace);
        $this->namespaceLen = \strlen($namespace);
        $this->createCacheItem = \Closure::bind(
            function ($key, $innerItem) use ($defaultLifetime, $poolHash) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $innerItem->get();
                $item->isHit = $innerItem->isHit();
                $item->defaultLifetime = $defaultLifetime;
                $item->innerItem = $innerItem;
                $item->poolHash = $poolHash;
                $innerItem->set(null);

                return $item;
            },
            null,
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

        return $f($key, $item);
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
        $expiry = $item["\0*\0expiry"];
        if (null === $expiry && 0 < $item["\0*\0defaultLifetime"]) {
            $expiry = time() + $item["\0*\0defaultLifetime"];
        }
        $innerItem = $item["\0*\0poolHash"] === $this->poolHash ? $item["\0*\0innerItem"] : $this->pool->getItem($this->namespace.$item["\0*\0key"]);
        $innerItem->set($item["\0*\0value"]);
        $innerItem->expiresAt(null !== $expiry ? \DateTime::createFromFormat('U', $expiry) : null);

        return $this->pool->$method($innerItem);
    }

    private function generateItems($items)
    {
        $f = $this->createCacheItem;

        foreach ($items as $key => $item) {
            if ($this->namespaceLen) {
                $key = substr($key, $this->namespaceLen);
            }

            yield $key => $f($key, $item);
        }
    }

    private function getId($key)
    {
        CacheItem::validateKey($key);

        return $this->namespace.$key;
    }
}
