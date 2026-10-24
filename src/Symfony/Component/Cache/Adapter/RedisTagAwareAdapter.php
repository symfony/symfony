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
use Predis\Connection\Cluster\ClusterInterface as Predis2ClusterInterface;
use Predis\Connection\Replication\ReplicationInterface as Predis2ReplicationInterface;
use Predis\Response\ErrorInterface;
use Predis\Response\Status;
use Relay\Relay;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;
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
 *  - Tag Sets keep references to expired or evicted items until prune() garbage-collects them.
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
    private const INVALIDATION_PREFIX = ":\1tags\1";
    private const INVALIDATION_BATCH_SIZE = 500;

    /**
     * detected eviction policy used on Redis server.
     */
    private string $redisEvictionPolicy;

    public function __construct(
        \Redis|Relay|\Relay\Cluster|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
        private string $namespace = '',
        int $defaultLifetime = 0,
        ?MarshallerInterface $marshaller = null,
    ) {
        if ($redis instanceof \Predis\ClientInterface && $redis->getConnection() instanceof ClusterInterface && !$redis->getConnection() instanceof PredisCluster) {
            throw new InvalidArgumentException(\sprintf('Unsupported Predis cluster connection: only "%s" is, "%s" given.', PredisCluster::class, get_debug_type($redis->getConnection())));
        }

        $isRelay = $redis instanceof Relay || $redis instanceof \Relay\Cluster;
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

    /**
     * Removes references to expired or evicted items from all the tag Sets of the pool,
     * and deletes the Sets that reference no existing item anymore.
     */
    public function prune(): bool
    {
        $prefix = '';
        $prefixLen = 0;
        if ($this->redis instanceof \Predis\ClientInterface) {
            $prefix = $this->redis->getOptions()->prefix ? $this->redis->getOptions()->prefix->getPrefix() : '';
            $prefixLen = \strlen($prefix);
        }

        $ok = true;
        $rootNamespace = '' === $this->namespace ? '' : $this->namespace.static::NS_SEPARATOR;

        if ($this->redis instanceof \Relay\Cluster) {
            $prefix = Relay::SCAN_PREFIX & $this->redis->getOption(Relay::OPT_SCAN) ? '' : $this->redis->getOption(Relay::OPT_PREFIX);
            $prefixLen = \strlen($this->redis->getOption(Relay::OPT_PREFIX) ?? '');
            $pattern = $prefix.$rootNamespace.'*'.static::TAGS_PREFIX.'*';

            foreach ($this->redis->_masters() as $ipAndPort) {
                $address = implode(':', $ipAndPort);
                $cursor = null;
                do {
                    $keys = $this->redis->scan($cursor, $address, $pattern, 1000);
                    if (isset($keys[1]) && \is_array($keys[1])) {
                        $cursor = $keys[0];
                        $keys = $keys[1];
                    }
                    foreach ($keys ?: [] as $key) {
                        if ($prefixLen) {
                            $key = substr($key, $prefixLen);
                        }
                        $ok = $this->pruneTagSet($key) && $ok;
                    }
                } while ($cursor);
            }

            return $ok;
        }

        $hosts = $this->getHosts();
        $host = reset($hosts);
        if ($host instanceof \Predis\Client) {
            $connection = $host->getConnection();

            if ($connection instanceof ReplicationInterface) {
                $hosts = [$host->getClientFor('master')];
            } elseif ($connection instanceof Predis2ReplicationInterface) {
                $connection->switchToMaster();
                $hosts = [$host];
            }
        }

        foreach ($hosts as $host) {
            if ($host instanceof Relay) {
                $prefix = Relay::SCAN_PREFIX & $host->getOption(Relay::OPT_SCAN) ? '' : $host->getOption(Relay::OPT_PREFIX);
                $prefixLen = \strlen($host->getOption(Relay::OPT_PREFIX) ?? '');
            } elseif (!$host instanceof \Predis\ClientInterface) {
                $prefix = \defined('Redis::SCAN_PREFIX') && (\Redis::SCAN_PREFIX & $host->getOption(\Redis::OPT_SCAN)) ? '' : $host->getOption(\Redis::OPT_PREFIX);
                $prefixLen = \strlen($host->getOption(\Redis::OPT_PREFIX) ?? '');
            }
            $pattern = $prefix.$rootNamespace.'*'.static::TAGS_PREFIX.'*';

            $cursor = null;
            do {
                $keys = $host instanceof \Predis\ClientInterface ? $host->scan($cursor ?? 0, ['MATCH' => $pattern, 'COUNT' => 1000]) : $host->scan($cursor, $pattern, 1000);
                if (isset($keys[1]) && \is_array($keys[1])) {
                    $cursor = $keys[0];
                    $keys = $keys[1];
                }
                foreach ($keys ?: [] as $key) {
                    if ($prefixLen) {
                        $key = substr($key, $prefixLen);
                    }
                    $ok = $this->pruneTagSet($key, $this->redis instanceof \RedisArray ? $host : null) && $ok;
                }
            } while ($cursor);
        }

        return $ok;
    }

    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $delTagData = []): array
    {
        $eviction = $this->getRedisEvictionPolicy();
        if ('noeviction' !== $eviction && !str_starts_with($eviction, 'volatile-')) {
            throw new LogicException(\sprintf('Redis maxmemory-policy setting "%s" is *not* supported by RedisTagAwareAdapter, use "noeviction" or "volatile-*" eviction policies.', $eviction));
        }

        $addTagData = [];
        $rootNamespace = '' === $this->namespace ? '' : $this->namespace.static::NS_SEPARATOR;
        foreach ($values as $id => $value) {
            foreach ($value['tags'] ?? [] as $tag) {
                $addTagData[$this->getId(static::TAGS_PREFIX.$tag, $rootNamespace)][] = $id;
            }
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
                CacheItem::log($this->logger, 'Failed to delete key "{key}": '.$result->getMessage(), ['key' => substr($id, \strlen($this->rootNamespace)), 'exception' => $result]);

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
        $fast = $this->isSingleServer() && !$this->isPacked($this->redis);
        $prefix = $fast ? $this->getRedisPrefix($this->redis) : '';
        $lua = <<<'EOLUA'
            if '1' == ARGV[3] and 0 == redis.call('SCARD', KEYS[3]) and redis.call('SCARD', KEYS[1]) <= 500 then
                for _, id in ipairs(redis.call('SMEMBERS', KEYS[1])) do
                    redis.call('DEL', ARGV[2]..id)
                end
                redis.call('DEL', KEYS[1])
                return 2
            end

            if 1 == redis.call('EXISTS', KEYS[1]) then
                redis.call('SADD', KEYS[3], ARGV[1])
                redis.call('RENAME', KEYS[1], KEYS[2])
            end

            return 1
            EOLUA;

        $pending = [];
        $results = $this->pipeline(function () use ($tagIds, $lua, $prefix, $fast, &$pending) {
            foreach ($tagIds as $id) {
                $redis = $this->redis instanceof \RedisArray ? $this->redis->_instance($this->redis->_target($id)) : $this->redis;
                $key = $this->getInvalidationKey($id, 'pending', $redis);
                $snapshot = $this->getInvalidationKey($id, bin2hex(random_bytes(16)), $redis);
                $pending[$id] = [$key, $redis];
                $args = [$id, $snapshot, $key, $snapshot, $prefix, $fast ? '1' : '0'];

                yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? array_merge([$lua, 3], $args) : [$lua, $args, 3];
            }
        });

        $count = 0;
        foreach ($results as $id => $result) {
            ++$count;
            if ($result instanceof \RedisException || $result instanceof \Relay\Exception || $result instanceof ErrorInterface) {
                throw new CacheException($result->getMessage());
            }
            if (2 === $result && $fast) {
                continue;
            }
            if (1 !== $result) {
                throw new CacheException('Failed to detach a tag set during invalidation.');
            }
            $this->drainInvalidations($id, ...$pending[$id]);
        }
        if ($count !== \count($tagIds)) {
            throw new CacheException('Failed to detach tag sets during invalidation.');
        }

        return true;
    }

    private function getRedisEvictionPolicy(): string
    {
        if (isset($this->redisEvictionPolicy)) {
            return $this->redisEvictionPolicy;
        }

        $hosts = $this->getHosts();
        $host = reset($hosts);
        if ($host instanceof \Predis\Client) {
            $connection = $host->getConnection();

            // Predis supports info command only on the master in replication environments
            if ($connection instanceof ReplicationInterface) {
                $hosts = [$host->getClientFor('master')];
            } elseif ($connection instanceof Predis2ReplicationInterface) {
                $connection->switchToMaster();
                $hosts = [$host];
            }
        }

        foreach ($hosts as $host) {
            $info = $host->info('Memory');
            $info = \is_array($info) ? ($info['Memory'] ?? $info) : [];

            if (isset($info['maxmemory_policy'])) {
                return $this->redisEvictionPolicy = $info['maxmemory_policy'];
            }

            // ElastiCache Serverless doesn't report its maxmemory-policy, which is always volatile-lru
            $info = $host->info();
            if (!\is_array($info)) {
                continue;
            }

            $server = $info['Server'] ?? $info;
            $replication = $info['Replication'] ?? $info;
            if ('cluster' === ($server['redis_mode'] ?? null) && '0' === (string) ($server['run_id'] ?? '')
                && ('Amazon ElastiCache' === ($server['os'] ?? null) || preg_match('~^ip=[a-z0-9.-]+\.serverless\.[a-z0-9-]+\.cache\.amazonaws\.com(?:\.cn)?(?:,|$)~iD', $replication['slave0'] ?? ''))
            ) {
                return $this->redisEvictionPolicy = 'volatile-lru';
            }
        }

        return $this->redisEvictionPolicy = '';
    }

    private function pruneTagSet(string $setId, ?object $redis = null): bool
    {
        $tagOffset = strpos($setId, static::TAGS_PREFIX);
        if (false !== $tagOffset && false !== $offset = strpos($setId, self::INVALIDATION_PREFIX, $tagOffset + \strlen(static::TAGS_PREFIX))) {
            $tagId = substr($setId, 0, $offset);

            try {
                $isSet = $this->executeScript("return redis.call('TYPE', KEYS[1]).ok == 'set' and 1 or 0", [$setId], [], $redis);
                if (0 === $isSet) {
                    return true;
                }
                if (1 !== $isSet) {
                    throw new CacheException('Failed to check tag invalidation metadata.');
                }
                if (str_starts_with(substr($setId, $offset + \strlen(self::INVALIDATION_PREFIX)), 'pending')) {
                    $this->drainInvalidations($tagId, $setId, $redis ?? $this->redis);
                } else {
                    $this->drainInvalidation($tagId, $setId, $redis ?? $this->redis);
                }

                return true;
            } catch (\Exception $e) {
                CacheItem::log($this->logger, 'Failed to prune tag set "{key}": '.$e->getMessage(), ['key' => $setId, 'exception' => $e]);

                return false;
            }
        }

        // Skipping keys that are not Sets tells apart unrelated keys that happen to match the scanned pattern

        $lua = <<<'EOLUA'
            if 'set' ~= redis.call('TYPE', KEYS[1]).ok then
                return false
            end

            return redis.call('SSCAN', KEYS[1], ARGV[1], 'COUNT', 1000)
            EOLUA;

        $cursor = '0';
        do {
            $results = $this->pipeline(function () use ($lua, $setId, $cursor) {
                yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? [$lua, 1, $setId, $cursor] : [$lua, [$setId, $cursor], 1];
            });

            $ids = null;
            foreach ($results as $result) {
                if ($result instanceof \RedisException || $result instanceof \Relay\Exception || $result instanceof ErrorInterface) {
                    CacheItem::log($this->logger, 'Failed to prune tag set "{key}": '.$result->getMessage(), ['key' => $setId, 'exception' => $result]);

                    return false;
                }
                if (\is_array($result)) {
                    [$cursor, $ids] = $result;
                }
            }

            if (null === $ids) {
                return true;
            }

            $dead = [];
            if ($ids) {
                foreach ($this->pipeline(static function () use ($ids) {
                    foreach ($ids as $id) {
                        yield 'exists' => [$id];
                    }
                }) as $id => $exists) {
                    if (!$exists) {
                        $dead[] = $id;
                    }
                }
            }

            if ($dead) {
                foreach ($this->pipeline(static function () use ($setId, $dead) {
                    yield 'sRem' => array_merge([$setId], $dead);
                }) as $result) {
                }

                // Items saved while pruning might have been removed from the Set right after their reference
                // was added to it; checking them again and restoring their reference makes no save be lost
                $revived = [];
                foreach ($this->pipeline(static function () use ($dead) {
                    foreach ($dead as $id) {
                        yield 'exists' => [$id];
                    }
                }) as $id => $exists) {
                    if ($exists) {
                        $revived[] = $id;
                    }
                }

                if ($revived) {
                    foreach ($this->pipeline(static function () use ($setId, $revived) {
                        yield 'sAdd' => array_merge([$setId], $revived);
                    }) as $result) {
                    }
                }
            }
        } while ('0' !== $cursor);

        return true;
    }

    private function executeScript(string $lua, array $keys, array $args = [], ?object $redis = null): mixed
    {
        $redis ??= $this->redis;
        $args = $redis instanceof \Predis\ClientInterface ? array_merge([$lua, \count($keys)], $keys, $args) : [$lua, array_merge($keys, $args), \count($keys)];

        foreach ($this->pipeline(static function () use ($args) { yield 'eval' => $args; }, $redis) as $result) {
            if ($result instanceof \RedisException || $result instanceof \Relay\Exception || $result instanceof ErrorInterface) {
                throw new CacheException($result->getMessage(), 0, $result instanceof \Throwable ? $result : null);
            }

            return $result;
        }

        throw new CacheException('Failed to execute tag invalidation.');
    }

    private function drainInvalidations(string $tagId, string $pending, object $redis): void
    {
        while ($snapshots = $this->getInvalidationMembers($pending, $redis)) {
            foreach ($snapshots as $snapshot) {
                if (!str_starts_with($snapshot, $tagId.self::INVALIDATION_PREFIX)) {
                    throw new CacheException('Invalid tag invalidation snapshot.');
                }
                $this->drainInvalidation($tagId, $snapshot, $redis);
                $this->removeInvalidationMembers($pending, [$snapshot], $redis);
            }
        }
    }

    private function drainInvalidation(string $tagId, string $snapshot, object $redis): void
    {
        $tag = substr($tagId, strpos($tagId, static::TAGS_PREFIX) + \strlen(static::TAGS_PREFIX));
        $serialized = $this->isPacked($redis);
        $packedValues = $this->isPacked($this->redis);
        $batch = !$packedValues && $this->isSingleServer();
        $count = $batch ? 250 : ($packedValues ? 1 : self::INVALIDATION_BATCH_SIZE);
        $readTags = <<<'EOLUA'
            local function readTags(id)
                local h = redis.call('GETRANGE', id, 0, 12)
                if h:len() ~= 13 or h:byte(1) ~= 0x9D or h:byte(6) ~= 0 or h:byte(10) ~= 0x5F then
                    return ''
                end
                local n = h:byte(13) + h:byte(12) * 256 + h:byte(11) * 65536
                return redis.call('GETRANGE', id, 13, 12 + n)
            end
            EOLUA;
        $delete = <<<'EOLUA'
            local function deleteTagged(id, expected)
                local h = redis.call('GETRANGE', id, 0, 12)
                if h:len() ~= 13 or h:byte(1) ~= 0x9D or h:byte(6) ~= 0 or h:byte(10) ~= 0x5F then
                    return 0
                end
                local n = h:byte(13) + h:byte(12) * 256 + h:byte(11) * 65536
                if redis.call('GETRANGE', id, 13, 12 + n) ~= expected then
                    return 0
                end
                local e = redis.pcall('UNLINK', id)
                return type(e) == 'number' and e or redis.call('DEL', id)
            end
            EOLUA;
        $deleteRaw = <<<'EOLUA'
            if redis.call('GET', KEYS[1]) ~= ARGV[1] then
                return 0
            end
            local e = redis.pcall('UNLINK', KEYS[1])
            return type(e) == 'number' and e or redis.call('DEL', KEYS[1])
            EOLUA;

        while ($members = $this->getInvalidationMembers($snapshot, $redis)) {
            foreach (array_chunk($members, $count) as $chunk) {
                if ($batch) {
                    $this->deleteTagBatch($tag, $chunk, $readTags, $delete);
                    $this->removeInvalidationMembers($snapshot, $chunk, $redis);
                    continue;
                }
                $ids = $packed = $clients = [];
                foreach ($chunk as $member) {
                    $id = $serialized ? $redis->_unpack($member) : $member;
                    if (!\is_string($id)) {
                        throw new CacheException('Invalid item identifier during tag invalidation.');
                    }
                    $ids[$member] = $id;
                    $clients[$id] = $this->redis instanceof \RedisArray ? $this->redis->_instance($this->redis->_target($id)) : $this->redis;
                    $packed[$id] = $this->redis instanceof \RedisArray ? $this->isPacked($clients[$id]) : $packedValues;
                }
                $results = $this->pipeline(function () use ($ids, $packed, $readTags) {
                    foreach ($ids as $id) {
                        $lua = $packed[$id] ? "return redis.call('GET', KEYS[1]) or ''" : $readTags."\nreturn readTags(KEYS[1])";
                        yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? [$lua, 1, $id] : [$lua, [$id], 1];
                    }
                });
                $deletions = [];
                $readCount = 0;
                foreach ($results as $id => $value) {
                    ++$readCount;
                    if ($value instanceof \RedisException || $value instanceof \Relay\Exception || $value instanceof ErrorInterface) {
                        throw new CacheException($value->getMessage());
                    }
                    if (!\is_string($value)) {
                        throw new CacheException('Failed to read an item during tag invalidation.');
                    }
                    if ('' === $value) {
                        continue;
                    }
                    $expected = $value;
                    if ($packed[$id]) {
                        $value = $clients[$id]->_unpack($value);
                        if (!\is_string($value)) {
                            throw new CacheException('Failed to decode an item during tag invalidation.');
                        }
                        if (13 >= \strlen($value) || "\x9D" !== $value[0] || "\0" !== $value[5] || "\x5F" !== $value[9]) {
                            continue;
                        }
                        $length = unpack('N', substr($value, 9, 4))[1] & 0x00FFFFFF;
                        $value = substr($value, 13, $length);
                    }
                    if ('' === $value) {
                        continue;
                    }
                    $tags = $this->marshaller->unmarshall($value);
                    if (\is_array($tags) && isset(array_fill_keys($tags, true)[$tag])) {
                        $deletions[$id] = $packed[$id] ? $expected : $value;
                    }
                }
                if ($readCount !== \count($ids)) {
                    throw new CacheException('Failed to read items during tag invalidation.');
                }
                if ($deletions) {
                    if ($this->redis instanceof Relay || $this->redis instanceof \Relay\Cluster) {
                        foreach ($deletions as $id => $expected) {
                            $lua = $packed[$id] ? "return redis.call('GET', KEYS[1]) or ''" : $readTags."\nreturn readTags(KEYS[1])";
                            $this->deleteRelayItem($this->redis, $id, $expected, $lua);
                        }
                    } else {
                        $deletedCount = 0;
                        foreach ($this->pipeline(function () use ($deletions, $delete, $deleteRaw, $packed) {
                            foreach ($deletions as $id => $expected) {
                                $lua = $packed[$id] ? $deleteRaw : $delete."\nreturn deleteTagged(KEYS[1], ARGV[1])";
                                yield 'eval' => $this->redis instanceof \Predis\ClientInterface ? [$lua, 1, $id, $expected] : [$lua, [$id, $expected], 1];
                            }
                        }) as $result) {
                            ++$deletedCount;
                            if ($result instanceof \RedisException || $result instanceof \Relay\Exception || $result instanceof ErrorInterface) {
                                throw new CacheException($result->getMessage());
                            }
                            if (!\is_int($result) || 0 > $result || 1 < $result) {
                                throw new CacheException('Failed to delete an item during tag invalidation.');
                            }
                        }
                        if ($deletedCount !== \count($deletions)) {
                            throw new CacheException('Failed to delete items during tag invalidation.');
                        }
                    }
                }
                $this->removeInvalidationMembers($snapshot, $chunk, $redis);
            }
        }
    }

    private function deleteRelayItem(Relay|\Relay\Cluster $redis, string $id, string $expected, string $lua): void
    {
        $watching = false;
        try {
            if (false === $redis->watch($id)) {
                throw new CacheException('Failed to watch an item during tag invalidation.');
            }
            $watching = true;
            $value = $redis->eval($lua, [$id], 1);
            if (!\is_string($value)) {
                throw new CacheException('Failed to read an item during tag invalidation.');
            }
            if ($value !== $expected) {
                return;
            }
            if (false === $redis->multi(Relay::MULTI) || false === $redis->del($id)) {
                throw new CacheException('Failed to queue item deletion during tag invalidation.');
            }
            $result = $redis->exec();
            $watching = false;
            if (!\is_array($result) || 1 !== \count($result) || !\is_int($result[0]) || 0 > $result[0] || 1 < $result[0]) {
                throw new CacheException('Failed to delete a watched item during tag invalidation.');
            }
        } finally {
            if (Relay::MULTI === $redis->getMode()) {
                if (false === $redis->discard()) {
                    throw new CacheException('Failed to discard tag invalidation.');
                }
            } elseif ($watching && false === $redis->unwatch()) {
                throw new CacheException('Failed to unwatch an item during tag invalidation.');
            }
        }
    }

    private function deleteTagBatch(string $tag, array $ids, string $readTags, string $delete): void
    {
        $lua = $readTags."\nlocal values = {} for i, id in ipairs(KEYS) do values[i] = readTags(id) end return values";
        $values = $this->executeScript($lua, $ids);
        if (!\is_array($values) || \count($ids) !== \count($values)) {
            throw new CacheException('Failed to read items during tag invalidation.');
        }

        $deletions = [];
        foreach ($values as $i => $value) {
            if (!\is_string($value)) {
                throw new CacheException('Failed to read an item during tag invalidation.');
            }
            if ('' === $value) {
                continue;
            }
            $tags = $this->marshaller->unmarshall($value);
            if (\is_array($tags) && isset(array_fill_keys($tags, true)[$tag])) {
                $deletions[$ids[$i]] = $value;
            }
        }
        if (!$deletions) {
            return;
        }

        $lua = $delete."\nlocal values = {} for i, id in ipairs(KEYS) do values[i] = deleteTagged(id, ARGV[i]) end return values";
        $deleted = $this->executeScript($lua, array_keys($deletions), array_values($deletions));
        if (!\is_array($deleted) || \count($deletions) !== \count($deleted)) {
            throw new CacheException('Failed to delete items during tag invalidation.');
        }
        foreach ($deleted as $result) {
            if (!\is_int($result) || 0 > $result || 1 < $result) {
                throw new CacheException('Failed to delete an item during tag invalidation.');
            }
        }
    }

    private function getInvalidationMembers(string $key, object $redis): array
    {
        $members = $this->executeScript("return redis.call('SRANDMEMBER', KEYS[1], ".self::INVALIDATION_BATCH_SIZE.')', [$key], [], $redis);
        if (!\is_array($members) || self::INVALIDATION_BATCH_SIZE < \count($members)) {
            throw new CacheException('Failed to read tag invalidation members.');
        }
        foreach ($members as $member) {
            if (!\is_string($member)) {
                throw new CacheException('Invalid tag invalidation member.');
            }
        }

        return $members;
    }

    private function removeInvalidationMembers(string $key, array $members, object $redis): void
    {
        $removed = $this->executeScript("return redis.call('SREM', KEYS[1], unpack(ARGV))", [$key], $members, $redis);
        if (!\is_int($removed) || 0 > $removed || \count($members) < $removed) {
            throw new CacheException('Failed to remove tag invalidation members.');
        }
    }

    private function isSingleServer(): bool
    {
        if ($this->redis instanceof \Predis\ClientInterface) {
            $connection = $this->redis->getConnection();
            if ($connection instanceof ClusterInterface || $connection instanceof Predis2ClusterInterface) {
                return false;
            }

            return true;
        }

        return $this->redis instanceof \Redis;
    }

    private function isPacked(object $redis): bool
    {
        if ($redis instanceof \Predis\ClientInterface) {
            return false;
        }
        $relay = $redis instanceof Relay || $redis instanceof \Relay\Cluster;

        return array_filter((array) $redis->getOption($relay ? Relay::OPT_SERIALIZER : \Redis::OPT_SERIALIZER)) || array_filter((array) $redis->getOption($relay ? Relay::OPT_COMPRESSION : \Redis::OPT_COMPRESSION));
    }

    private function getRedisPrefix(object $redis): string
    {
        if ($redis instanceof \Predis\ClientInterface) {
            return $redis->getOptions()->prefix ? $redis->getOptions()->prefix->getPrefix() : '';
        }

        return $redis->getOption($redis instanceof Relay || $redis instanceof \Relay\Cluster ? Relay::OPT_PREFIX : \Redis::OPT_PREFIX) ?? '';
    }

    private function getInvalidationKey(string $id, string $suffix, object $redis): string
    {
        $prefix = $this->getRedisPrefix($redis);
        $key = $prefix.$id;
        $suffix = self::INVALIDATION_PREFIX.$suffix;
        if (false !== $start = strpos($key, '{')) {
            if (false !== $end = strpos($key, '}', $start + 1)) {
                if ($start + 1 !== $end) {
                    return $id.$suffix;
                }
            }
        }

        // Append two correction bytes to keep RENAME keys in the same Redis Cluster slot.
        // Redis uses CRC16/XMODEM, which isn't available through hash_algos().
        $slot = self::crc16($key) & 0x3FFF;
        $crc = self::crc16($key.$suffix);
        for ($i = 0; $i < 4; ++$i) {
            $value = $slot | ($i << 14);
            for ($j = 0; $j < 16; ++$j) {
                $value = ($value & 1) ? (($value ^ 0x1021) >> 1) | 0x8000 : $value >> 1;
            }
            $bytes = pack('n', $crc ^ $value);
            if (!str_contains($bytes, '{') && !str_contains($bytes, '}')) {
                return $id.$suffix.$bytes;
            }
        }

        throw new \LogicException('Cannot compute a Redis tag invalidation key.');
    }

    private static function crc16(string $key): int
    {
        $crc = 0;
        for ($i = 0, $length = \strlen($key); $i < $length; ++$i) {
            $crc ^= \ord($key[$i]) << 8;
            for ($j = 0; $j < 8; ++$j) {
                $crc = (($crc << 1) ^ (($crc & 0x8000) ? 0x1021 : 0)) & 0xFFFF;
            }
        }

        return $crc;
    }
}
