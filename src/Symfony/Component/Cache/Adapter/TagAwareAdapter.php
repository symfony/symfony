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
 * Implements simple and robust tag-based invalidation algorithm suitable for use with volatile caches.
 *
 * Tags point to a separate keys a values of which are current tag versions. Values of tagged items contain
 * tag versions as an integral part and remain valid until any of their tag versions are changed.
 * Invalidation is achieved by deleting tags, thereby ensuring change of their versions even when the storage is out of
 * space. When versions of non-existing tags are requested for item commits or for validation of retrieved items,
 * adapter creates tags and assigns a new random version to them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Sergey Belyshkin <sbelyshkin@gmail.com>
 */
class TagAwareAdapter implements TagAwareAdapterInterface, TagAwareCacheInterface, PruneableInterface, ResettableInterface, LoggerAwareInterface
{
    use ContractsTrait;
    use LoggerAwareTrait;

    public const TAGS_PREFIX = "\0tags\0";
    private const ITEM_PREFIX = '$';
    private const TAG_PREFIX = '#';
    private const MAX_NUMBER_OF_KNOWN_TAG_VERSIONS = 1000;

    private array $deferred = [];
    private AdapterInterface $pool;
    private AdapterInterface $tags;
    private array $knownTagVersions = [];
    private float $knownTagVersionsTtl;

    private static \Closure $unpackCacheItem;
    private static \Closure $unsetCacheItem;
    private static \Closure $computeAndPackItems;
    private static \Closure $extractTagsFromItems;
    private static \Closure $saveTags;

    public function __construct(AdapterInterface $itemsPool, AdapterInterface $tagsPool = null, float $knownTagVersionsTtl = 0.15)
    {
        $this->pool = $itemsPool;
        $this->tags = $tagsPool ?? $itemsPool;
        $this->knownTagVersionsTtl = $knownTagVersionsTtl;
        self::$unpackCacheItem ??= \Closure::bind(
            static function (CacheItem $item, string $key): array {
                $item->key = $key;
                $item->isTaggable = true;
                if (!$item->isHit) {
                    return [];
                }
                $value = $item->value;
                if (!\is_array($value) || !((['$', '#', '^'] === ($arrayKeys = array_keys($value)) && \is_string($value['^']) || ['$', '#'] === $arrayKeys) && \is_array($value['#']))) {
                    $item->isHit = false;
                    $item->value = null;

                    return [];
                }
                $item->value = $value['$'];
                if ($value['#']) {
                    $tags = [];
                    foreach ($value['#'] as $tag => $tagVersion) {
                        $tags[$tag] = $tag;
                    }
                    $item->metadata[CacheItem::METADATA_TAGS] = $tags;
                }
                if (isset($value['^'])) {
                    $m = unpack('Ne/Vc', str_pad($value['^'], 8, "\x00"));
                    $item->metadata[CacheItem::METADATA_EXPIRY] = $m['e'];
                    $item->metadata[CacheItem::METADATA_CTIME] = $m['c'];
                }

                return $value['#'];
            },
            null,
            CacheItem::class
        );
        self::$unsetCacheItem ??= \Closure::bind(
            static function (CacheItem $item) {
                $item->isHit = false;
                $item->value = null;
                $item->metadata = [];
            },
            null,
            CacheItem::class
        );
        $getPrefixedKeyMethod = \Closure::fromCallable([$this, 'getPrefixedKey']);
        self::$computeAndPackItems ??= \Closure::bind(
            static function ($deferred, $tagVersions) use ($getPrefixedKeyMethod) {
                $packedItems = [];
                foreach ($deferred as $key => $item) {
                    $itemTagVersions = [];
                    $metadata = $item->newMetadata;
                    if (isset($metadata[CacheItem::METADATA_TAGS])) {
                        foreach ($metadata[CacheItem::METADATA_TAGS] as $tag) {
                            if (!isset($tagVersions[$tag])) {
                                // Don't save items without full set of valid tags
                                continue 2;
                            }
                            $itemTagVersions[$tag] = $tagVersions[$tag];
                        }
                    }
                    // Pack the value, tags and meta data.
                    $value = ['$' => $item->value, '#' => $itemTagVersions];
                    if (isset($metadata[CacheItem::METADATA_CTIME])) {
                        $ctime = $metadata[CacheItem::METADATA_CTIME];
                        // 1. 03:14:08 UTC on Tuesday, 19 January 2038 timestamp will reach 0x7FFFFFFF and 32-bit systems
                        // will go back to Unix Epoch, but on 64-bit systems it's OK to use first 32 bits of timestamp
                        // till 06:28:15 UTC on Sunday, 7 February 2106, when it'll reach 0xFFFFFFFF.
                        // 2. CTIME is packed as an 8/16/24/32-bits integer. For reference, 24 bits are able to reflect
                        // intervals up to 4 hours 39 minutes 37 seconds and 215 ms, but in most cases 8 bits are enough.
                        $length = 4 + ($ctime <= 255 ? 1 : ($ctime <= 65535 ? 2 : ($ctime <= 16777215 ? 3 : 4)));
                        $value['^'] = substr(pack('NV', (int) ceil($metadata[CacheItem::METADATA_EXPIRY]), $ctime), 0, $length);
                    }
                    $packedItem = clone $item;
                    $packedItem->metadata = $packedItem->newMetadata = [];
                    $packedItem->key = $getPrefixedKeyMethod($key);
                    $packedItem->value = $value;
                    $packedItems[] = $packedItem;

                    $item->metadata = $metadata;
                }

                return $packedItems;
            },
            null,
            CacheItem::class
        );
        self::$extractTagsFromItems ??= \Closure::bind(
            static function ($deferred) {
                $uniqueTags = [];
                foreach ($deferred as $item) {
                    $uniqueTags += $item->newMetadata[CacheItem::METADATA_TAGS] ?? [];
                }

                return $uniqueTags;
            },
            null,
            CacheItem::class
        );
        self::$saveTags ??= \Closure::bind(
            static function (AdapterInterface $tagsAdapter, array $tags) {
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

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags): bool
    {
        $ids = [];
        foreach ($tags as $tag) {
            \assert('' !== CacheItem::validateKey($tag));
            unset($this->knownTagVersions[$tag]);
            $ids[] = static::TAG_PREFIX.$tag;
        }

        return !$tags || $this->tags->deleteItems($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(mixed $key): bool
    {
        if (\is_string($key) && isset($this->deferred[$key])) {
            $this->commit();
        }

        if (!$this->pool->hasItem($this->getPrefixedKey($key))) {
            return false;
        }

        $item = $this->getItem($key);

        return $item->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(mixed $key): CacheItem
    {
        $prefixedKey = $this->getPrefixedKey($key);

        if (isset($this->deferred[$key])) {
            $this->commit();
        }

        $item = $this->pool->getItem($prefixedKey);
        $itemTagVersions = (self::$unpackCacheItem)($item, $key);

        while (true) {
            $knownTagVersions = $this->knownTagVersions;
            $now = microtime(true);
            foreach ($itemTagVersions as $itemTag => $itemTagVersion) {
                if (($knownTagVersions[$itemTag][0] ?? 0.0) < $now || $knownTagVersions[$itemTag][1] !== $itemTagVersion) {
                    break 2;
                }
            }

            return $item;
        }
        $knownTagVersions = null;

        $tagVersions = $this->getTagVersions(array_keys($itemTagVersions));
        foreach ($itemTagVersions as $itemTag => $itemTagVersion) {
            if (!isset($tagVersions[$itemTag]) || $tagVersions[$itemTag] !== $itemTagVersion) {
                (self::$unsetCacheItem)($item);
                break;
            }
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        $items = $itemIdsMap = $itemTagVersions = $tagVersions = [];
        $commit = false;

        foreach ($keys as $key) {
            $itemIdsMap[$this->getPrefixedKey($key)] = $key;
            $commit = $commit || isset($this->deferred[$key]);
        }

        if ($commit) {
            $this->commit();
        }

        $validateAgainstKnownTagVersions = !empty($this->knownTagVersions);
        $f = self::$unpackCacheItem;
        foreach ($this->pool->getItems(array_keys($itemIdsMap)) as $itemId => $item) {
            $key = $itemIdsMap[$itemId];
            $itemTagVersions[$key] = $t = ($f)($item, $key);
            $items[$key] = $item;
            if (!$t) {
                continue;
            }
            $tagVersions += $t;
            if ($validateAgainstKnownTagVersions) {
                foreach ($t as $tag => $tagVersion) {
                    if ($tagVersions[$tag] !== $tagVersion) {
                        $validateAgainstKnownTagVersions = false;
                        break;
                    }
                }
            }
        }
        $itemIdsMap = null;

        return $this->generateItems($items, $itemTagVersions, $tagVersions, $validateAgainstKnownTagVersions);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $prefix = ''): bool
    {
        if ($this->pool instanceof AdapterInterface) {
            $isPoolCleared = $this->pool->clear(self::ITEM_PREFIX.$prefix);
        } else {
            $isPoolCleared = $this->pool->clear();
        }

        if ($this->tags instanceof AdapterInterface) {
            $isTagPoolCleared = $this->tags->clear(static::TAG_PREFIX.$prefix);
        } else {
            $isTagPoolCleared = $this->tags->clear();
        }

        if ('' !== $prefix) {
            foreach ($this->deferred as $key => $item) {
                if (str_starts_with($key, $prefix)) {
                    unset($this->deferred[$key]);
                }
            }
        } else {
            $this->deferred = [];
        }

        return $isPoolCleared && $isTagPoolCleared;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(mixed $key): bool
    {
        return $this->pool->deleteItem($this->getPrefixedKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        $prefixedKeys = array_map([$this, 'getPrefixedKey'], $keys);

        return $this->pool->deleteItems($prefixedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): bool
    {
        return $this->saveDeferred($item) && $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item): bool
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
    public function commit(): bool
    {
        if (!$this->deferred) {
            return true;
        }

        $uniqueTags = (self::$extractTagsFromItems)($this->deferred);
        $tagVersions = $this->getTagVersions($uniqueTags);
        $packedItems = (self::$computeAndPackItems)($this->deferred, $tagVersions);
        $allItemsArePacked = \count($this->deferred) === \count($packedItems);
        $this->deferred = [];

        foreach ($packedItems as $item) {
            $this->pool->saveDeferred($item);
        }

        return $this->pool->commit() && $allItemsArePacked;
    }

    /**
     * {@inheritdoc}
     */
    public function prune(): bool
    {
        $isPruned = $this->pool instanceof PruneableInterface && $this->pool->prune();

        return $this->tags instanceof PruneableInterface && $this->tags->prune() && $isPruned;
    }

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

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        $this->commit();
    }

    private function generateItems(array $items, array $itemTagVersions, array $tagVersions, bool $validateAgainstKnownTagVersions = false): \Generator
    {
        if ($validateAgainstKnownTagVersions) {
            $knownTagVersions = $this->knownTagVersions;
            $now = microtime(true);
            foreach ($itemTagVersions as $itemTag => $itemTagVersion) {
                if (($knownTagVersions[$itemTag][0] ?? 0.0) < $now || $knownTagVersions[$itemTag][1] !== $itemTagVersion) {
                    $validateAgainstKnownTagVersions = false;
                    break;
                }
            }
        }
        if (!$validateAgainstKnownTagVersions) {
            $tagVersions = $this->getTagVersions(array_keys($tagVersions));
            foreach ($items as $key => $item) {
                foreach ($itemTagVersions[$key] as $itemTag => $itemTagVersion) {
                    if (!isset($tagVersions[$itemTag]) || $tagVersions[$itemTag] !== $itemTagVersion) {
                        (self::$unsetCacheItem)($item);
                        break;
                    }
                }
                yield $key => $item;
            }
        } else {
            foreach ($items as $key => $item) {
                yield $key => $item;
            }
        }
    }

    /**
     * Loads tag versions from or creates them in the tag pool, and updates the cache of known tag versions.
     *
     * May return only a part of requested tags or even none of them if for some reason they cannot be read or created.
     *
     * @throws InvalidArgumentException
     *
     * @return string[]
     */
    private function getTagVersions(array $tags): array
    {
        if (!$tags) {
            return [];
        }

        $tagIdsMap = $tagVersions = $createdTagVersions = $createdTags = [];
        foreach ($tags as $tag) {
            $tagIdsMap[static::TAG_PREFIX.$tag] = $tag;
        }
        ksort($tagIdsMap);

        if (0.0 < $this->knownTagVersionsTtl) {
            $now = microtime(true);
            $knownTagVersionsExpiration = $now + $this->knownTagVersionsTtl;
            if (self::MAX_NUMBER_OF_KNOWN_TAG_VERSIONS < \count($this->knownTagVersions)) {
                $this->knownTagVersions = array_filter($this->knownTagVersions, static function ($v) use ($now) { return $now < $v[0]; });
            }
            foreach ($this->tags->getItems(array_keys($tagIdsMap)) as $tagId => $version) {
                $tag = $tagIdsMap[$tagId];
                if ($version->isHit()) {
                    $tagVersions[$tag] = $version->get();
                    $this->knownTagVersions[$tag] = [$knownTagVersionsExpiration, $tagVersions[$tag]];
                    continue;
                }
                $createdTags[] = $version->set($newTagVersion ??= random_bytes(8));
                $createdTagVersions[$tag] = $newTagVersion;
                unset($this->knownTagVersions[$tag]);
            }
        } else {
            foreach ($this->tags->getItems(array_keys($tagIdsMap)) as $tagId => $version) {
                $tag = $tagIdsMap[$tagId];
                if ($version->isHit()) {
                    $tagVersions[$tag] = $version->get();
                    continue;
                }
                $createdTags[] = $version->set($newTagVersion ??= random_bytes(8));
                $createdTagVersions[$tag] = $newTagVersion;
            }
        }

        if ($createdTags && !(self::$saveTags)($this->tags, $createdTags)) {
            $createdTagVersions = [];
        }

        return $tagVersions += $createdTagVersions;
    }

    private function getPrefixedKey($key): string
    {
        \assert('' !== CacheItem::validateKey($key));

        return static::ITEM_PREFIX.$key;
    }
}
