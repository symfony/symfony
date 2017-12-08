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
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ProxyTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TagAwareAdapter implements TagAwareAdapterInterface, PruneableInterface, ResettableInterface
{
    const TAGS_PREFIX = "\0tags\0";

    use ProxyTrait;

    private $deferred = array();
    private $createCacheItem;
    private $setCacheItemTags;
    private $getTagsByKey;
    private $invalidateTags;
    private $tags;

    public function __construct(AdapterInterface $itemsPool, AdapterInterface $tagsPool = null)
    {
        $this->pool = $itemsPool;
        $this->tags = $tagsPool ?: $itemsPool;
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, CacheItem $protoItem) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->defaultLifetime = $protoItem->defaultLifetime;
                $item->expiry = $protoItem->expiry;
                $item->innerItem = $protoItem->innerItem;
                $item->poolHash = $protoItem->poolHash;

                return $item;
            },
            null,
            CacheItem::class
        );
        $this->setCacheItemTags = \Closure::bind(
            function (CacheItem $item, $key, array &$itemTags) {
                if (!$item->isHit) {
                    return $item;
                }
                if (isset($itemTags[$key])) {
                    foreach ($itemTags[$key] as $tag => $version) {
                        $item->prevTags[$tag] = $tag;
                    }
                    unset($itemTags[$key]);
                } else {
                    $item->value = null;
                    $item->isHit = false;
                }

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
        $this->invalidateTags = \Closure::bind(
            function (AdapterInterface $tagsAdapter, array $tags) {
                foreach ($tagsAdapter->getItems($tags) as $v) {
                    $v->set(1 + (int) $v->get());
                    $v->defaultLifetime = 0;
                    $v->expiry = null;
                    $tagsAdapter->saveDeferred($v);
                }

                return $tagsAdapter->commit();
            },
            null,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags)
    {
        foreach ($tags as $k => $tag) {
            if ('' !== $tag && is_string($tag)) {
                $tags[$k] = $tag.static::TAGS_PREFIX;
            }
        }
        $f = $this->invalidateTags;

        return $f($this->tags, $tags);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
        if (!$this->pool->hasItem($key)) {
            return false;
        }
        if (!$itemTags = $this->pool->getItem(static::TAGS_PREFIX.$key)->get()) {
            return true;
        }

        foreach ($this->getTagVersions(array($itemTags)) as $tag => $version) {
            if ($itemTags[$tag] !== $version) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        foreach ($this->getItems(array($key)) as $item) {
            return $item;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        if ($this->deferred) {
            $this->commit();
        }
        $tagKeys = array();

        foreach ($keys as $key) {
            if ('' !== $key && is_string($key)) {
                $key = static::TAGS_PREFIX.$key;
                $tagKeys[$key] = $key;
            }
        }

        try {
            $items = $this->pool->getItems($tagKeys + $keys);
        } catch (InvalidArgumentException $e) {
            $this->pool->getItems($keys); // Should throw an exception

            throw $e;
        }

        return $this->generateItems($items, $tagKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();

        return $this->pool->clear();
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
        foreach ($keys as $key) {
            if ('' !== $key && is_string($key)) {
                $keys[] = static::TAGS_PREFIX.$key;
            }
        }

        return $this->pool->deleteItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
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
            $items = $this->deferred;
            foreach ($items as $key => $item) {
                if (!$this->pool->saveDeferred($item)) {
                    unset($this->deferred[$key]);
                    $ok = false;
                }
            }

            $f = $this->getTagsByKey;
            $tagsByKey = $f($items);
            $this->deferred = array();
            $tagVersions = $this->getTagVersions($tagsByKey);
            $f = $this->createCacheItem;

            foreach ($tagsByKey as $key => $tags) {
                $this->pool->saveDeferred($f(static::TAGS_PREFIX.$key, array_intersect_key($tagVersions, $tags), $items[$key]));
            }
        }

        return $this->pool->commit() && $ok;
    }

    public function __destruct()
    {
        $this->commit();
    }

    private function generateItems($items, array $tagKeys)
    {
        $bufferedItems = $itemTags = array();
        $f = $this->setCacheItemTags;

        foreach ($items as $key => $item) {
            if (!$tagKeys) {
                yield $key => $f($item, static::TAGS_PREFIX.$key, $itemTags);
                continue;
            }
            if (!isset($tagKeys[$key])) {
                $bufferedItems[$key] = $item;
                continue;
            }

            unset($tagKeys[$key]);
            $itemTags[$key] = $item->get() ?: array();

            if (!$tagKeys) {
                $tagVersions = $this->getTagVersions($itemTags);

                foreach ($itemTags as $key => $tags) {
                    foreach ($tags as $tag => $version) {
                        if ($tagVersions[$tag] !== $version) {
                            unset($itemTags[$key]);
                            continue 2;
                        }
                    }
                }
                $tagVersions = $tagKeys = null;

                foreach ($bufferedItems as $key => $item) {
                    yield $key => $f($item, static::TAGS_PREFIX.$key, $itemTags);
                }
                $bufferedItems = null;
            }
        }
    }

    private function getTagVersions(array $tagsByKey)
    {
        $tagVersions = array();

        foreach ($tagsByKey as $tags) {
            $tagVersions += $tags;
        }

        if ($tagVersions) {
            $tags = array();
            foreach ($tagVersions as $tag => $version) {
                $tagVersions[$tag] = $tag.static::TAGS_PREFIX;
                $tags[$tag.static::TAGS_PREFIX] = $tag;
            }
            foreach ($this->tags->getItems($tagVersions) as $tag => $version) {
                $tagVersions[$tags[$tag]] = $version->get() ?: 0;
            }
        }

        return $tagVersions;
    }
}
