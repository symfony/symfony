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

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\AbstractAdapterTrait;
use Symfony\Component\Cache\Traits\ContractsTrait;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Abstract for native TagAware adapters.
 *
 * To keep info on tags, the tags are both serialized as part of cache value and provided as tag ids
 * to Adapters on operations when needed for storage to doSave(), doDelete() & doInvalidate().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 *
 * @internal
 */
abstract class AbstractTagAwareAdapter implements TagAwareAdapterInterface, TagAwareCacheInterface, LoggerAwareInterface, ResettableInterface
{
    use AbstractAdapterTrait;
    use ContractsTrait;

    private const TAGS_PREFIX = "\0tags\0";

    protected function __construct(string $namespace = '', int $defaultLifetime = 0)
    {
        $this->namespace = '' === $namespace ? '' : CacheItem::validateKey($namespace).':';
        $this->defaultLifetime = $defaultLifetime;
        if (null !== $this->maxIdLength && \strlen($namespace) > $this->maxIdLength - 24) {
            throw new InvalidArgumentException(sprintf('Namespace must be %d chars max, %d given ("%s").', $this->maxIdLength - 24, \strlen($namespace), $namespace));
        }
        self::$createCacheItem ?? self::$createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->isTaggable = true;
                // If structure does not match what we expect return item as is (no value and not a hit)
                if (!\is_array($value) || !\array_key_exists('value', $value)) {
                    return $item;
                }
                $item->isHit = $isHit;
                // Extract value, tags and meta data from the cache value
                $item->value = $value['value'];
                $item->metadata[CacheItem::METADATA_TAGS] = $value['tags'] ?? [];
                if (isset($value['meta'])) {
                    // For compactness these values are packed, & expiry is offset to reduce size
                    $v = unpack('Ve/Nc', $value['meta']);
                    $item->metadata[CacheItem::METADATA_EXPIRY] = $v['e'] + CacheItem::METADATA_EXPIRY_OFFSET;
                    $item->metadata[CacheItem::METADATA_CTIME] = $v['c'];
                }

                return $item;
            },
            null,
            CacheItem::class
        );
        self::$mergeByLifetime ?? self::$mergeByLifetime = \Closure::bind(
            static function ($deferred, &$expiredIds, $getId, $tagPrefix, $defaultLifetime) {
                $byLifetime = [];
                $now = microtime(true);
                $expiredIds = [];

                foreach ($deferred as $key => $item) {
                    $key = (string) $key;
                    if (null === $item->expiry) {
                        $ttl = 0 < $defaultLifetime ? $defaultLifetime : 0;
                    } elseif (!$item->expiry) {
                        $ttl = 0;
                    } elseif (0 >= $ttl = (int) (0.1 + $item->expiry - $now)) {
                        $expiredIds[] = $getId($key);
                        continue;
                    }
                    // Store Value and Tags on the cache value
                    if (isset(($metadata = $item->newMetadata)[CacheItem::METADATA_TAGS])) {
                        $value = ['value' => $item->value, 'tags' => $metadata[CacheItem::METADATA_TAGS]];
                        unset($metadata[CacheItem::METADATA_TAGS]);
                    } else {
                        $value = ['value' => $item->value, 'tags' => []];
                    }

                    if ($metadata) {
                        // For compactness, expiry and creation duration are packed, using magic numbers as separators
                        $value['meta'] = pack('VN', (int) (0.1 + $metadata[self::METADATA_EXPIRY] - self::METADATA_EXPIRY_OFFSET), $metadata[self::METADATA_CTIME]);
                    }

                    // Extract tag changes, these should be removed from values in doSave()
                    $value['tag-operations'] = ['add' => [], 'remove' => []];
                    $oldTags = $item->metadata[CacheItem::METADATA_TAGS] ?? [];
                    foreach (array_diff($value['tags'], $oldTags) as $addedTag) {
                        $value['tag-operations']['add'][] = $getId($tagPrefix.$addedTag);
                    }
                    foreach (array_diff($oldTags, $value['tags']) as $removedTag) {
                        $value['tag-operations']['remove'][] = $getId($tagPrefix.$removedTag);
                    }

                    $byLifetime[$ttl][$getId($key)] = $value;
                    $item->metadata = $item->newMetadata;
                }

                return $byLifetime;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * Persists several cache items immediately.
     *
     * @param array   $values        The values to cache, indexed by their cache identifier
     * @param int     $lifetime      The lifetime of the cached values, 0 for persisting until manual cleaning
     * @param array[] $addTagData    Hash where key is tag id, and array value is list of cache id's to add to tag
     * @param array[] $removeTagData Hash where key is tag id, and array value is list of cache id's to remove to tag
     *
     * @return array The identifiers that failed to be cached or a boolean stating if caching succeeded or not
     */
    abstract protected function doSave(array $values, int $lifetime, array $addTagData = [], array $removeTagData = []): array;

    /**
     * Removes multiple items from the pool and their corresponding tags.
     *
     * @param array $ids An array of identifiers that should be removed from the pool
     */
    abstract protected function doDelete(array $ids): bool;

    /**
     * Removes relations between tags and deleted items.
     *
     * @param array $tagData Array of tag => key identifiers that should be removed from the pool
     */
    abstract protected function doDeleteTagRelations(array $tagData): bool;

    /**
     * Invalidates cached items using tags.
     *
     * @param string[] $tagIds An array of tags to invalidate, key is tag and value is tag id
     */
    abstract protected function doInvalidate(array $tagIds): bool;

    /**
     * Delete items and yields the tags they were bound to.
     */
    protected function doDeleteYieldTags(array $ids): iterable
    {
        foreach ($this->doFetch($ids) as $id => $value) {
            yield $id => \is_array($value) && \is_array($value['tags'] ?? null) ? $value['tags'] : [];
        }

        $this->doDelete($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        $ok = true;
        $byLifetime = (self::$mergeByLifetime)($this->deferred, $expiredIds, \Closure::fromCallable([$this, 'getId']), self::TAGS_PREFIX, $this->defaultLifetime);
        $retry = $this->deferred = [];

        if ($expiredIds) {
            // Tags are not cleaned up in this case, however that is done on invalidateTags().
            $this->doDelete($expiredIds);
        }
        foreach ($byLifetime as $lifetime => $values) {
            try {
                $values = $this->extractTagData($values, $addTagData, $removeTagData);
                $e = $this->doSave($values, $lifetime, $addTagData, $removeTagData);
            } catch (\Exception $e) {
            }
            if (true === $e || [] === $e) {
                continue;
            }
            if (\is_array($e) || 1 === \count($values)) {
                foreach (\is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = get_debug_type($v);
                    $message = sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': '.$e->getMessage() : '.');
                    CacheItem::log($this->logger, $message, ['key' => substr($id, \strlen($this->namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }

        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $byLifetime[$lifetime][$id];
                    $values = $this->extractTagData([$id => $v], $addTagData, $removeTagData);
                    $e = $this->doSave($values, $lifetime, $addTagData, $removeTagData);
                } catch (\Exception $e) {
                }
                if (true === $e || [] === $e) {
                    continue;
                }
                $ok = false;
                $type = get_debug_type($v);
                $message = sprintf('Failed to save key "{key}" of type %s%s', $type, $e instanceof \Exception ? ': '.$e->getMessage() : '.');
                CacheItem::log($this->logger, $message, ['key' => substr($id, \strlen($this->namespace)), 'exception' => $e instanceof \Exception ? $e : null, 'cache-adapter' => get_debug_type($this)]);
            }
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        if (!$keys) {
            return true;
        }

        $ok = true;
        $ids = [];
        $tagData = [];

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        try {
            foreach ($this->doDeleteYieldTags(array_values($ids)) as $id => $tags) {
                foreach ($tags as $tag) {
                    $tagData[$this->getId(self::TAGS_PREFIX.$tag)][] = $id;
                }
            }
        } catch (\Exception $e) {
            $ok = false;
        }

        try {
            if ((!$tagData || $this->doDeleteTagRelations($tagData)) && $ok) {
                return true;
            }
        } catch (\Exception $e) {
        }

        // When bulk-delete failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->doDelete([$id])) {
                    continue;
                }
            } catch (\Exception $e) {
            }
            $message = 'Failed to delete key "{key}"'.($e instanceof \Exception ? ': '.$e->getMessage() : '.');
            CacheItem::log($this->logger, $message, ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
            $ok = false;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags): bool
    {
        if (empty($tags)) {
            return false;
        }

        $tagIds = [];
        foreach (array_unique($tags) as $tag) {
            $tagIds[] = $this->getId(self::TAGS_PREFIX.$tag);
        }

        if ($this->doInvalidate($tagIds)) {
            return true;
        }

        return false;
    }

    /**
     * Extracts tags operation data from $values set in mergeByLifetime, and returns values without it.
     */
    private function extractTagData(array $values, ?array &$addTagData, ?array &$removeTagData): array
    {
        $addTagData = $removeTagData = [];
        foreach ($values as $id => $value) {
            foreach ($value['tag-operations']['add'] as $tag => $tagId) {
                $addTagData[$tagId][] = $id;
            }

            foreach ($value['tag-operations']['remove'] as $tag => $tagId) {
                $removeTagData[$tagId][] = $id;
            }

            unset($values[$id]['tag-operations']);
        }

        return $values;
    }
}
