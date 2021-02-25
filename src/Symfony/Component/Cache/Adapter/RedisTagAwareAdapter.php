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
use Predis\Response\Status;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Marshaller\DeflateMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\Traits\RedisTrait;

/**
 * Stores tag id <> cache id relationship as a Redis Set, lookup on invalidation using RENAME+SMEMBERS.
 *
 * Set (tag relation info) is stored without expiry (non-volatile), while cache always gets an expiry (volatile) even
 * if not set by caller. Thus if you configure redis with the right eviction policy you can be safe this tag <> cache
 * relationship survives eviction (cache cleanup when Redis runs out of memory).
 *
 * Requirements:
 *  - Client: PHP Redis or Predis
 *            Note: Due to lack of RENAME support it is NOT recommended to use Cluster on Predis, instead use phpredis.
 *  - Server: Redis 2.8+
 *            Configured with any `volatile-*` eviction policy, OR `noeviction` if it will NEVER fill up memory
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
class RedisTagAwareAdapter extends AbstractTagAwareAdapter
{
    use RedisTrait;

    /**
     * Limits for how many keys are deleted in batch.
     */
    private const BULK_DELETE_LIMIT = 10000;

    /**
     * On cache items without a lifetime set, we set it to 100 days. This is to make sure cache items are
     * preferred to be evicted over tag Sets, if eviction policy is configured according to requirements.
     */
    private const DEFAULT_CACHE_TTL = 8640000;

    /**
     * @var string|null detected eviction policy used on Redis server
     */
    private $redisEvictionPolicy;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface $redisClient     The redis client
     * @param string                                                   $namespace       The default namespace
     * @param int                                                      $defaultLifetime The default lifetime
     */
    public function __construct($redisClient, string $namespace = '', int $defaultLifetime = 0, MarshallerInterface $marshaller = null)
    {
        if ($redisClient instanceof \Predis\ClientInterface && $redisClient->getConnection() instanceof ClusterInterface && !$redisClient->getConnection() instanceof PredisCluster) {
            throw new InvalidArgumentException(sprintf('Unsupported Predis cluster connection: only "%s" is, "%s" given.', PredisCluster::class, \get_class($redisClient->getConnection())));
        }

        if (\defined('Redis::OPT_COMPRESSION') && ($redisClient instanceof \Redis || $redisClient instanceof \RedisArray || $redisClient instanceof \RedisCluster)) {
            $compression = $redisClient->getOption(\Redis::OPT_COMPRESSION);

            foreach (\is_array($compression) ? $compression : [$compression] as $c) {
                if (\Redis::COMPRESSION_NONE !== $c) {
                    throw new InvalidArgumentException(sprintf('phpredis compression must be disabled when using "%s", use "%s" instead.', static::class, DeflateMarshaller::class));
                }
            }
        }

        $this->init($redisClient, $namespace, $defaultLifetime, new TagAwareMarshaller($marshaller));
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $delTagData = []): array
    {
        $eviction = $this->getRedisEvictionPolicy();
        if ('noeviction' !== $eviction && 0 !== strpos($eviction, 'volatile-')) {
            throw new LogicException(sprintf('Redis maxmemory-policy setting "%s" is *not* supported by RedisTagAwareAdapter, use "noeviction" or  "volatile-*" eviction policies.', $eviction));
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

    /**
     * {@inheritdoc}
     */
    protected function doDeleteYieldTags(array $ids): iterable
    {
        $lua = <<<'EOLUA'
            local v = redis.call('GET', KEYS[1])
            redis.call('DEL', KEYS[1])

            if not v or v:len() <= 13 or v:byte(1) ~= 0x9D or v:byte(6) ~= 0 or v:byte(10) ~= 0x5F then
                return ''
            end

            return v:sub(14, 13 + v:byte(13) + v:byte(12) * 256 + v:byte(11) * 65536)
EOLUA;

        if ($this->redis instanceof \Predis\ClientInterface) {
            $evalArgs = [$lua, 1, &$id];
        } else {
            $evalArgs = [$lua, [&$id], 1];
        }

        $results = $this->pipeline(function () use ($ids, &$id, $evalArgs) {
            foreach ($ids as $id) {
                yield 'eval' => $evalArgs;
            }
        });

        foreach ($results as $id => $result) {
            try {
                yield $id => !\is_string($result) || '' === $result ? [] : $this->marshaller->unmarshall($result);
            } catch (\Exception $e) {
                yield $id => [];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doDeleteTagRelations(array $tagData): bool
    {
        $this->pipeline(static function () use ($tagData) {
            foreach ($tagData as $tagId => $idList) {
                array_unshift($idList, $tagId);
                yield 'sRem' => $idList;
            }
        })->rewind();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doInvalidate(array $tagIds): bool
    {
        if (!$this->redis instanceof \Predis\ClientInterface || !$this->redis->getConnection() instanceof PredisCluster) {
            $movedTagSetIds = $this->renameKeys($this->redis, $tagIds);
        } else {
            $clusterConnection = $this->redis->getConnection();
            $tagIdsByConnection = new \SplObjectStorage();
            $movedTagSetIds = [];

            foreach ($tagIds as $id) {
                $connection = $clusterConnection->getConnectionByKey($id);
                $slot = $tagIdsByConnection[$connection] ?? $tagIdsByConnection[$connection] = new \ArrayObject();
                $slot[] = $id;
            }

            foreach ($tagIdsByConnection as $connection) {
                $slot = $tagIdsByConnection[$connection];
                $movedTagSetIds = array_merge($movedTagSetIds, $this->renameKeys(new $this->redis($connection, $this->redis->getOptions()), $slot->getArrayCopy()));
            }
        }

        // No Sets found
        if (!$movedTagSetIds) {
            return false;
        }

        // Now safely take the time to read the keys in each set and collect ids we need to delete
        $tagIdSets = $this->pipeline(static function () use ($movedTagSetIds) {
            foreach ($movedTagSetIds as $movedTagId) {
                yield 'sMembers' => [$movedTagId];
            }
        });

        // Return combination of the temporary Tag Set ids and their values (cache ids)
        $ids = array_merge($movedTagSetIds, ...iterator_to_array($tagIdSets, false));

        // Delete cache in chunks to avoid overloading the connection
        foreach (array_chunk(array_unique($ids), self::BULK_DELETE_LIMIT) as $chunkIds) {
            $this->doDelete($chunkIds);
        }

        return true;
    }

    /**
     * Renames several keys in order to be able to operate on them without risk of race conditions.
     *
     * Filters out keys that do not exist before returning new keys.
     *
     * @see https://redis.io/commands/rename
     * @see https://redis.io/topics/cluster-spec#keys-hash-tags
     *
     * @return array Filtered list of the valid moved keys (only those that existed)
     */
    private function renameKeys($redis, array $ids): array
    {
        $newIds = [];
        $uniqueToken = bin2hex(random_bytes(10));

        $results = $this->pipeline(static function () use ($ids, $uniqueToken) {
            foreach ($ids as $id) {
                yield 'rename' => [$id, '{'.$id.'}'.$uniqueToken];
            }
        }, $redis);

        foreach ($results as $id => $result) {
            if (true === $result || ($result instanceof Status && Status::get('OK') === $result)) {
                // Only take into account if ok (key existed), will be false on phpredis if it did not exist
                $newIds[] = '{'.$id.'}'.$uniqueToken;
            }
        }

        return $newIds;
    }

    private function getRedisEvictionPolicy(): string
    {
        if (null !== $this->redisEvictionPolicy) {
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
            $info = $info['Memory'] ?? $info;

            return $this->redisEvictionPolicy = $info['maxmemory_policy'];
        }

        return $this->redisEvictionPolicy = '';
    }
}
