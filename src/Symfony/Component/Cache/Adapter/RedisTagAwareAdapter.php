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

use Predis\Connection\Aggregate\ClusterInterface;
use Predis\Connection\Aggregate\PredisCluster;
use Predis\Connection\Aggregate\ReplicationInterface;
use Predis\Response\ErrorInterface;
use Predis\Response\Status;
use Relay\Relay;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Marshaller\DeflateMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\RedisTrait;

/**
 * Stores tag id <> cache id relationship as a Redis Set.
 *
 * Set (tag relation info) is stored without expiry (non-volatile), while cache always gets an expiry (volatile) even
 * if not set by caller. Thus if you configure redis with the right eviction policy you can be safe this tag <> cache
 * relationship survives eviction (cache cleanup when Redis runs out of memory).
 *
 * Redis server 2.8+ with any `volatile-*` eviction policy, OR `noeviction` if you're sure memory will NEVER fill up
 *
 * Design limitations:
 *  - Max 4 billion cache keys per cache tag as limited by Redis Set datatype.
 *    E.g. If you use a "all" items tag for expiry instead of clear(), that limits you to 4 billion cache items also.
 *
 * @see https://redis.io/topics/lru-cache#eviction-policies Documentation for Redis eviction policies.
 * @see https://redis.io/topics/data-types#sets Documentation for Redis Set datatype.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 */
class RedisTagAwareAdapter extends AbstractTagAwareAdapter implements PruneableInterface
{
    use RedisTrait;

    /**
     * On cache items without a lifetime set, we set it to 100 days. This is to make sure cache items are
     * preferred to be evicted over tag Sets, if eviction policy is configured according to requirements.
     */
    private const DEFAULT_CACHE_TTL = 8640000;

    /**
     * detected eviction policy used on Redis server.
     */
    private string $redisEvictionPolicy;

    public function __construct(
        \Redis|Relay|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
        private string $namespace = '',
        int $defaultLifetime = 0,
        ?MarshallerInterface $marshaller = null,
    ) {
        if ($redis instanceof \Predis\ClientInterface && $redis->getConnection() instanceof ClusterInterface && !$redis->getConnection() instanceof PredisCluster) {
            throw new InvalidArgumentException(\sprintf('Unsupported Predis cluster connection: only "%s" is, "%s" given.', PredisCluster::class, get_debug_type($redis->getConnection())));
        }

        $isRelay = $redis instanceof Relay;
        if ($isRelay || \defined('Redis::OPT_COMPRESSION') && \in_array($redis::class, [\Redis::class, \RedisArray::class, \RedisCluster::class], true)) {
            $compression = $redis->getOption($isRelay ? Relay::OPT_COMPRESSION : \Redis::OPT_COMPRESSION);

            foreach (\is_array($compression) ? $compression : [$compression] as $c) {
                if ($isRelay ? Relay::COMPRESSION_NONE : \Redis::COMPRESSION_NONE !== $c) {
                    throw new InvalidArgumentException(\sprintf('redis compression must be disabled when using "%s", use "%s" instead.', static::class, DeflateMarshaller::class));
                }
            }
        }

        $this->init($redis, $namespace, $defaultLifetime, new TagAwareMarshaller($marshaller));
    }

    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $delTagData = []): array
    {
        $eviction = $this->getRedisEvictionPolicy();
        if ('noeviction' !== $eviction && !str_starts_with($eviction, 'volatile-')) {
            throw new LogicException(\sprintf('Redis maxmemory-policy setting "%s" is *not* supported by RedisTagAwareAdapter, use "noeviction" or "volatile-*" eviction policies.', $eviction));
        }

        // serialize values
        if (!$serialized = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        // While pipeline isn't supported on RedisCluster, other setups will at least benefit from doing this in one op
        $results = $this->pipeline(static function () use ($serialized, $lifetime, $addTagData, $delTagData, $failed) {
            // Store cache items, force a ttl if none is set, as there is no MSETEX we need to set each one
            foreach ($serialized as $id => $value) {
                yield 'setEx' => [
                    $id,
                    0 >= $lifetime ? self::DEFAULT_CACHE_TTL : $lifetime,
                    $value,
                ];
            }

            // Add and Remove Tags
            foreach ($addTagData as $tagId => $ids) {
                if (!$failed || $ids = array_diff($ids, $failed)) {
                    yield 'sAdd' => array_merge([$tagId], $ids);
                }
            }

            foreach ($delTagData as $tagId => $ids) {
                if (!$failed || $ids = array_diff($ids, $failed)) {
                    yield 'sRem' => array_merge([$tagId], $ids);
                }
            }
        });

        foreach ($results as $id => $result) {
            // Skip results of SADD/SREM operations, they'll be 1 or 0 depending on if set value already existed or not
            if (is_numeric($result)) {
                continue;
            }
            // setEx results
            if (true !== $result && (!$result instanceof Status || Status::get('OK') !== $result)) {
                $failed[] = $id;
            }
        }

        return $failed;
    }

    protected function doDeleteYieldTags(array $ids): iterable
    {
        $lua = <<<'EOLUA'
            local v = redis.call('GET', KEYS[1])
            local e = redis.pcall('UNLINK', KEYS[1])

            if type(e) ~= 'number' then
                redis.call('DEL', KEYS[1])
            end

            if not v or v:len() <= 13 or v:byte(1) ~= 0x9D or v:byte(6) ~= 0 or v:byte(10) ~= 0x5F then
                return ''
            end

            return v:sub(14, 13 + v:byte(13) + v:byte(12) * 256 + v:byte(11) * 65536)
EOLUA;

        $results = $this->pipeline(function () use ($ids, $lua) {
            foreach ($ids as $id) {
                yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? [$lua, 1, $id] : [$lua, [$id], 1];
            }
        });

        foreach ($results as $id => $result) {
            if ($result instanceof \RedisException || $result instanceof \Relay\Exception || $result instanceof ErrorInterface) {
                CacheItem::log($this->logger, 'Failed to delete key "{key}": '.$result->getMessage(), ['key' => substr($id, \strlen($this->namespace)), 'exception' => $result]);

                continue;
            }

            try {
                yield $id => !\is_string($result) || '' === $result ? [] : $this->marshaller->unmarshall($result);
            } catch (\Exception) {
                yield $id => [];
            }
        }
    }

    protected function doDeleteTagRelations(array $tagData): bool
    {
        $results = $this->pipeline(static function () use ($tagData) {
            foreach ($tagData as $tagId => $idList) {
                array_unshift($idList, $tagId);
                yield 'sRem' => $idList;
            }
        });
        foreach ($results as $result) {
            // no-op
        }

        return true;
    }

    protected function doInvalidate(array $tagIds): bool
    {
        // This script scans the set of items linked to tag: it empties the set
        // and removes the linked items. When the set is still not empty after
        // the scan, it means we're in cluster mode and that the linked items
        // are on other nodes: we move the links to a temporary set and we
        // garbage collect that set from the client side.

        $lua = <<<'EOLUA'
            redis.replicate_commands()

            local cursor = '0'
            local id = KEYS[1]
            repeat
                local result = redis.call('SSCAN', id, cursor, 'COUNT', 5000);
                cursor = result[1];
                local rems = {}

                for _, v in ipairs(result[2]) do
                    local ok, _ = pcall(redis.call, 'DEL', ARGV[1]..v)
                    if ok then
                        table.insert(rems, v)
                    end
                end
                if 0 < #rems then
                    redis.call('SREM', id, unpack(rems))
                end
            until '0' == cursor;

            redis.call('SUNIONSTORE', '{'..id..'}'..id, id)
            redis.call('DEL', id)

            return redis.call('SSCAN', '{'..id..'}'..id, '0', 'COUNT', 5000)
EOLUA;

        $results = $this->pipeline(function () use ($tagIds, $lua) {
            if ($this->redis instanceof \Predis\ClientInterface) {
                $prefix = $this->redis->getOptions()->prefix ? $this->redis->getOptions()->prefix->getPrefix() : '';
            } elseif (\is_array($prefix = $this->redis->getOption($this->redis instanceof Relay ? Relay::OPT_PREFIX : \Redis::OPT_PREFIX) ?? '')) {
                $prefix = current($prefix);
            }

            foreach ($tagIds as $id) {
                yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? [$lua, 1, $id, $prefix] : [$lua, [$id, $prefix], 1];
            }
        });

        $lua = <<<'EOLUA'
            redis.replicate_commands()

            local id = KEYS[1]
            local cursor = table.remove(ARGV)
            redis.call('SREM', '{'..id..'}'..id, unpack(ARGV))

            return redis.call('SSCAN', '{'..id..'}'..id, cursor, 'COUNT', 5000)
EOLUA;

        $success = true;
        foreach ($results as $id => $values) {
            if ($values instanceof \RedisException || $values instanceof \Relay\Exception || $values instanceof ErrorInterface) {
                CacheItem::log($this->logger, 'Failed to invalidate key "{key}": '.$values->getMessage(), ['key' => substr($id, \strlen($this->namespace)), 'exception' => $values]);
                $success = false;

                continue;
            }

            [$cursor, $ids] = $values;

            while ($ids || '0' !== $cursor) {
                $this->doDelete($ids);

                $evalArgs = [$id, $cursor];
                array_splice($evalArgs, 1, 0, $ids);

                if ($this->redis instanceof \Predis\ClientInterface) {
                    array_unshift($evalArgs, $lua, 1);
                } else {
                    $evalArgs = [$lua, $evalArgs, 1];
                }

                $results = $this->pipeline(function () use ($evalArgs) {
                    yield 'eval' => $evalArgs;
                });

                foreach ($results as [$cursor, $ids]) {
                    // no-op
                }
            }
        }

        return $success;
    }

    private function getRedisEvictionPolicy(): string
    {
        if (isset($this->redisEvictionPolicy)) {
            return $this->redisEvictionPolicy;
        }

        $hosts = $this->getHosts();
        $host = reset($hosts);
        if ($host instanceof \Predis\Client && $host->getConnection() instanceof ReplicationInterface) {
            // Predis supports info command only on the master in replication environments
            $hosts = [$host->getClientFor('master')];
        }

        foreach ($hosts as $host) {
            $info = $host->info('Memory');

            if (false === $info || null === $info || $info instanceof ErrorInterface) {
                continue;
            }

            $info = $info['Memory'] ?? $info;

            return $this->redisEvictionPolicy = $info['maxmemory_policy'] ?? '';
        }

        return $this->redisEvictionPolicy = '';
    }

    private function getPrefix(): string
    {
        if ($this->redis instanceof \Predis\ClientInterface) {
            $prefix = $this->redis->getOptions()->prefix ? $this->redis->getOptions()->prefix->getPrefix() : '';
        } elseif (\is_array($prefix = $this->redis->getOption(\Redis::OPT_PREFIX) ?? '')) {
            $prefix = current($prefix);
        }

        return $prefix;
    }

    /**
     * Returns all existing tag keys from the cache.
     *
     * @TODO Verify the LUA scripts are redis-cluster safe.
     */
    protected function getAllTagKeys(): array
    {
        $tagKeys = [];
        $prefix = $this->getPrefix();
        // need to trim the \0 for lua script
        $tagsPrefix = trim(self::TAGS_PREFIX);

        // get all SET entries which are tagged
        $getTagsLua = <<<'EOLUA'
            redis.replicate_commands()
            local cursor = ARGV[1]
            local prefix = ARGV[2]
            local tagPrefix = string.gsub(KEYS[1], prefix, "")
            return redis.call('SCAN', cursor, 'COUNT', 5000, 'MATCH', '*' .. tagPrefix .. '*', 'TYPE', 'set')
        EOLUA;
        $cursor = null;
        do {
            $results = $this->pipeline(function () use ($getTagsLua, $cursor, $prefix, $tagsPrefix) {
                yield 'eval' => [$getTagsLua, [$tagsPrefix, $cursor, $prefix], 1];
            });

            $setKeys = $results->valid() ? iterator_to_array($results) : [];
            [$cursor, $ids] = $setKeys[$tagsPrefix] ?? [null, null];
            // merge the fetched ids together
            $tagKeys = array_merge($tagKeys, $ids);
        } while ($cursor = (int) $cursor);

        return $tagKeys;
    }

    /**
     * Checks all tags in the cache for orphaned items and creates a "report" array.
     *
     * By default, only completely orphaned tag keys are reported. If
     * compressMode is enabled the report will include all tag keys
     * that have any orphaned references to cache items
     *
     * @TODO Verify the LUA scripts are redis-cluster safe.
     * @TODO Is there anything that can be done to reduce memory footprint?
     *
     * @return array{tagKeys: string[], orphanedTagKeys: string[], orphanedTagReferenceKeys?: array<string, string[]>}
     *                                                                                                                 tagKeys: List of all tags in the cache.
     *                                                                                                                 orphanedTagKeys: List of tags that only reference orphaned cache items.
     *                                                                                                                 orphanedTagReferenceKeys: List of all orphaned cache item references per tag.
     *                                                                                                                 Keyed by tag, value is the list of orphaned cache item keys.
     */
    private function getOrphanedTagsStats(bool $compressMode = false): array
    {
        $prefix = $this->getPrefix();
        $tagKeys = $this->getAllTagKeys();

        // lua for fetching all entries/content from a SET
        $getSetContentLua = <<<'EOLUA'
            redis.replicate_commands()
            local cursor = ARGV[1]
            return redis.call('SSCAN', KEYS[1], cursor, 'COUNT', 5000)
        EOLUA;

        $orphanedTagReferenceKeys = [];
        $orphanedTagKeys = [];
        // Iterate over each tag and check if its entries reference orphaned
        // cache items.
        foreach ($tagKeys as $tagKey) {
            $tagKey = substr($tagKey, \strlen($prefix));
            $cursor = null;
            $hasExistingKeys = false;
            do {
                // Fetch all referenced cache keys from the tag entry.
                $results = $this->pipeline(function () use ($getSetContentLua, $tagKey, $cursor) {
                    yield 'eval' => [$getSetContentLua, [$tagKey, $cursor], 1];
                });
                [$cursor, $referencedCacheKeys] = $results->valid() ? $results->current() : [null, null];

                if (!empty($referencedCacheKeys)) {
                    // Counts how many of the referenced cache items exist.
                    $existingCacheKeysResult = $this->pipeline(function () use ($referencedCacheKeys) {
                        yield 'exists' => $referencedCacheKeys;
                    });
                    $existingCacheKeysCount = $existingCacheKeysResult->valid() ? $existingCacheKeysResult->current() : 0;
                    $hasExistingKeys = $hasExistingKeys || ($existingCacheKeysCount > 0 ?? false);

                    // If compression mode is enabled and the count between
                    // referenced and existing cache keys differs collect the
                    // missing references.
                    if ($compressMode && \count($referencedCacheKeys) > $existingCacheKeysCount) {
                        // In order to create the delta each single reference
                        // has to be checked.
                        foreach ($referencedCacheKeys as $cacheKey) {
                            $existingCacheKeyResult = $this->pipeline(function () use ($cacheKey) {
                                yield 'exists' => [$cacheKey];
                            });
                            if ($existingCacheKeyResult->valid() && !$existingCacheKeyResult->current()) {
                                $orphanedTagReferenceKeys[$tagKey][] = $cacheKey;
                            }
                        }
                    }
                    // Stop processing cursors in case compression mode is
                    // disabled and the tag references existing keys.
                    if (!$compressMode && $hasExistingKeys) {
                        break;
                    }
                }
            } while ($cursor = (int) $cursor);
            if (!$hasExistingKeys) {
                $orphanedTagKeys[] = $tagKey;
            }
        }

        $stats = ['orphanedTagKeys' => $orphanedTagKeys, 'tagKeys' => $tagKeys];
        if ($compressMode) {
            $stats['orphanedTagReferenceKeys'] = $orphanedTagReferenceKeys;
        }

        return $stats;
    }

    /**
     * @TODO Verify the LUA scripts are redis-cluster safe.
     */
    private function pruneOrphanedTags(bool $compressMode = false): bool
    {
        $success = true;
        $orphanedTagsStats = $this->getOrphanedTagsStats($compressMode);

        // Delete all tags that don't reference any existing cache item.
        foreach ($orphanedTagsStats['orphanedTagKeys'] as $orphanedTagKey) {
            $result = $this->pipeline(function () use ($orphanedTagKey) {
                yield 'del' => [$orphanedTagKey];
            });
            if (!$result->valid() || 1 !== $result->current()) {
                $success = false;
            }
        }
        // If orphaned cache key references are provided prune them too.
        if (!empty($orphanedTagsStats['orphanedTagReferenceKeys'])) {
            // lua for deleting member from a SET
            $removeSetMemberLua = <<<'EOLUA'
                redis.replicate_commands()
                return redis.call('SREM', KEYS[1], KEYS[2])
            EOLUA;
            // Loop through all tags with orphaned cache item references.
            foreach ($orphanedTagsStats['orphanedTagReferenceKeys'] as $tagKey => $orphanedCacheKeys) {
                // Remove each cache item reference from the tag set.
                foreach ($orphanedCacheKeys as $orphanedCacheKey) {
                    $result = $this->pipeline(function () use ($tagKey, $orphanedCacheKey) {
                        yield 'srem' => [$tagKey, $orphanedCacheKey];
                    });
                    if (!$result->valid() || 1 !== $result->current()) {
                        $success = false;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * @TODO Make compression mode flag configurable.
     */
    public function prune(): bool
    {
        return $this->pruneOrphanedTags(true);
    }
}
