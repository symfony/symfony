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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ContractsTrait;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Implements simple and robust tag-based invalidation suitable for use with volatile caches.
 *
 * This adapter works by storing a version for each tags. When saving an item, it is stored together with its tags and
 * their corresponding versions. When retrieving an item, those tag versions are compared to the current version of
 * each tags. Invalidation is achieved by deleting tags, thereby ensuring that their versions change even when the
 * storage is out of space. When versions of non-existing tags are requested for item commits, this adapter assigns a
 * new random version to them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Sergey Belyshkin <sbelyshkin@gmail.com>
 */
class TagAwareAdapter implements TagAwareAdapterInterface, TagAwareCacheInterface, PruneableInterface, ResettableInterface, LoggerAwareInterface
{
    use ContractsTrait;
    use LoggerAwareTrait;

    public const TAGS_PREFIX = "\1tags\1";

    private array $deferred = [];
    private AdapterInterface $pool;
    private AdapterInterface $tags;
    private array $knownTagVersions = [];
    private float $knownTagVersionsTtl;

    private static \Closure $setCacheItemTags;
    private static \Closure $setTagVersions;
    private static \Closure $getTagsByKey;
    private static \Closure $saveTags;

    public function __construct(AdapterInterface $itemsPool, ?AdapterInterface $tagsPool = null, float $knownTagVersionsTtl = 0.15)
    {
        $this->pool = $itemsPool;
        $this->tags = $tagsPool ?? $itemsPool;
        $this->knownTagVersionsTtl = $knownTagVersionsTtl;
        self::$setCacheItemTags ??= \Closure::bind(
            static function (array $items, array $itemTags) {
                foreach ($items as $key => $item) {
                    $item->isTaggable = true;

                    if (isset($itemTags[$key])) {
                        $tags = array_keys($itemTags[$key]);
                        $item->metadata[CacheItem::METADATA_TAGS] = array_combine($tags, $tags);
                    } else {
                        $item->value = null;
                        $item->isHit = false;
                        $item->metadata = [];
                    }
                }

                return $items;
            },
            null,
            CacheItem::class
        );
        self::$setTagVersions ??= \Closure::bind(
            static function (array $items, array $tagVersions) {
                foreach ($items as $item) {
                    $item->newMetadata[CacheItem::METADATA_TAGS] = array_intersect_key($tagVersions, $item->newMetadata[CacheItem::METADATA_TAGS] ?? []);
                }
            },
            null,
            CacheItem::class
        );
        self::$getTagsByKey ??= \Closure::bind(
            static function ($deferred) {
                $tagsByKey = [];
                foreach ($deferred as $key => $item) {
                    $tagsByKey[$key] = $item->newMetadata[CacheItem::METADATA_TAGS] ?? [];
                    $item->metadata = $item->newMetadata;
                }

                return $tagsByKey;
            },
            null,
            CacheItem::class
        );
        self::$saveTags ??= \Closure::bind(
            static function (AdapterInterface $tagsAdapter, array $tags) {
                ksort($tags);

                foreach ($tags as $v) {
                    $v->expiry = 0;
                    $tagsAdapter->saveDeferred($v);
                }

                return $tagsAdapter->commit();
            },
            null,
            CacheItem::class
        );
    }

    public function invalidateTags(array $tags): bool
    {
        $ids = [];
        foreach ($tags as $tag) {
            \assert('' !== CacheItem::validateKey($tag));
            unset($this->knownTagVersions[$tag]);
            $ids[] = $tag.static::TAGS_PREFIX;
        }

        return !$tags || $this->tags->deleteItems($ids);
    }

    public function hasItem(mixed $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function getItem(mixed $key): CacheItem
    {
        foreach ($this->getItems([$key]) as $item) {
            return $item;
        }
    }

    public function getItems(array $keys = []): iterable
    {
        $tagKeys = [];
        $commit = false;

        foreach ($keys as $key) {
            if ('' !== $key && \is_string($key)) {
                $commit = $commit || isset($this->deferred[$key]);
            }
        }

        if ($commit) {
            $this->commit();
        }

        try {
            $items = $this->pool->getItems($keys);
        } catch (InvalidArgumentException $e) {
            $this->pool->getItems($keys); // Should throw an exception

            throw $e;
        }

        $bufferedItems = $itemTags = [];

        foreach ($items as $key => $item) {
            if (null !== $tags = $item->getMetadata()[CacheItem::METADATA_TAGS] ?? null) {
                $itemTags[$key] = $tags;
            }

            $bufferedItems[$key] = $item;

            if (null === $tags) {
                $key = "\0tags\0".$key;
                $tagKeys[$key] = $key; // BC with pools populated before v6.1
            }
        }

        if ($tagKeys) {
            foreach ($this->pool->getItems($tagKeys) as $key => $item) {
                if ($item->isHit()) {
                    $itemTags[substr($key, \strlen("\0tags\0"))] = $item->get() ?: [];
                }
            }
        }

        $tagVersions = $this->getTagVersions($itemTags, false);
        foreach ($itemTags as $key => $tags) {
            foreach ($tags as $tag => $version) {
                if ($tagVersions[$tag] !== $version) {
                    unset($itemTags[$key]);
                    continue 2;
                }
            }
        }
        $tagVersions = null;

        return (self::$setCacheItemTags)($bufferedItems, $itemTags);
    }

    public function clear(string $prefix = ''): bool
    {
        if ('' !== $prefix) {
            foreach ($this->deferred as $key => $item) {
                if (str_starts_with($key, $prefix)) {
                    unset($this->deferred[$key]);
                }
            }
        } else {
            $this->deferred = [];
        }

        if ($this->pool instanceof AdapterInterface) {
            return $this->pool->clear($prefix);
        }

        return $this->pool->clear();
    }

    public function deleteItem(mixed $key): bool
    {
        return $this->deleteItems([$key]);
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            if ('' !== $key && \is_string($key)) {
                $keys[] = "\0tags\0".$key; // BC with pools populated before v6.1
            }
        }

        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return $this->commit();
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        if (!$items = $this->deferred) {
            return true;
        }

        $tagVersions = $this->getTagVersions((self::$getTagsByKey)($items), true);
        (self::$setTagVersions)($items, $tagVersions);

        $ok = true;
        foreach ($items as $key => $item) {
            if ($this->pool->saveDeferred($item)) {
                unset($this->deferred[$key]);
            } else {
                $ok = false;
            }
        }
        $ok = $this->pool->commit() && $ok;

        $tagVersions = array_keys($tagVersions);
        (self::$setTagVersions)($items, array_combine($tagVersions, $tagVersions));

        return $ok;
    }

    public function prune(): bool
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->commit();
        $this->knownTagVersions = [];
        $this->pool instanceof ResettableInterface && $this->pool->reset();
        $this->tags instanceof ResettableInterface && $this->tags->reset();
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    /**
     * @return void
     */
    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->commit();
    }

    private function getTagVersions(array $tagsByKey, bool $persistTags): array
    {
        $tagVersions = [];
        $fetchTagVersions = $persistTags;

        foreach ($tagsByKey as $tags) {
            $tagVersions += $tags;
            if ($fetchTagVersions) {
                continue;
            }
            foreach ($tags as $tag => $version) {
                if ($tagVersions[$tag] !== $version) {
                    $fetchTagVersions = true;
                }
            }
        }

        if (!$tagVersions) {
            return [];
        }

        $now = microtime(true);
        $tags = [];
        foreach ($tagVersions as $tag => $version) {
            $tags[$tag.static::TAGS_PREFIX] = $tag;
            $knownTagVersion = $this->knownTagVersions[$tag] ?? [0, null];
            if ($fetchTagVersions || $now > $knownTagVersion[0] || $knownTagVersion[1] !== $version) {
                // reuse previously fetched tag versions until the expiration
                $fetchTagVersions = true;
            }
        }

        if (!$fetchTagVersions) {
            return $tagVersions;
        }

        $newTags = [];
        $newVersion = null;
        $expiration = $now + $this->knownTagVersionsTtl;
        foreach ($this->tags->getItems(array_keys($tags)) as $tag => $version) {
            unset($this->knownTagVersions[$tag = $tags[$tag]]); // update FIFO
            if (null !== $tagVersions[$tag] = $version->get()) {
                $this->knownTagVersions[$tag] = [$expiration, $tagVersions[$tag]];
            } elseif ($persistTags) {
                $newTags[$tag] = $version->set($newVersion ??= random_bytes(6));
                $tagVersions[$tag] = $newVersion;
                $this->knownTagVersions[$tag] = [$expiration, $newVersion];
            }
        }

        if ($newTags) {
            (self::$saveTags)($this->tags, $newTags);
        }

        while ($now > ($this->knownTagVersions[$tag = array_key_first($this->knownTagVersions)][0] ?? \INF)) {
            unset($this->knownTagVersions[$tag]);
        }

        return $tagVersions;
    }
}
