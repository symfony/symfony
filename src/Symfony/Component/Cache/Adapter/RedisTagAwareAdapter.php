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

use Predis;
use Predis\Connection\Aggregate\ClusterInterface;
use Predis\Response\Status;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Traits\RedisTrait;

/**
 * Stores tag id <> cache id relationship as a Redis Set, lookup on invalidation using sPOP.
 *
 * Set (tag relation info) is stored without expiry (non-volatile), while cache always gets an expiry (volatile) even
 * if not set by caller. Thus if you configure redis with the right eviction policy you can be safe this tag <> cache
 * relationship survives eviction (cache cleanup when Redis runs out of memory).
 *
 * Requirements:
 *  - Server: Redis 3.2+
 *  - Client: PHP Redis 3.1.3+ OR Predis
 *  - Redis Server(s) configured with any `volatile-*` eviction policy, OR `noeviction` if it will NEVER fill up memory
 *
 * Design limitations:
 *  - Max 2 billion cache keys per cache tag
 *    E.g. If you use a "all" items tag for expiry instead of clear(), that limits you to 2 billion cache items as well
 *
 * @see https://redis.io/topics/lru-cache#eviction-policies Documentation for Redis eviction policies.
 * @see https://redis.io/topics/data-types#sets Documentation for Redis Set datatype.
 * @see https://redis.io/commands/spop Documentation for sPOP operation, capable of retriving AND emptying a Set at once.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 *
 * @experimental in 4.3
 */
class RedisTagAwareAdapter extends AbstractTagAwareAdapter
{
    use RedisTrait;

    /**
     * Redis "Set" can hold more than 4 billion members, here we limit ourselves to PHP's > 2 billion max int (32Bit).
     */
    private const POP_MAX_LIMIT = 2147483647 - 1;

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
     * @var bool|null
     */
    private $redisServerSupportSPOP = null;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient     The redis client
     * @param string                                          $namespace       The default namespace
     * @param int                                             $defaultLifetime The default lifetime
     * @param MarshallerInterface|null                        $marshaller
     *
     * @throws \Symfony\Component\Cache\Exception\LogicException If phpredis with version lower than 3.1.3.
     */
    public function __construct($redisClient, string $namespace = '', int $defaultLifetime = 0, MarshallerInterface $marshaller = null)
    {
        $this->init($redisClient, $namespace, $defaultLifetime, $marshaller);

        // Make sure php-redis is 3.1.3 or higher configured for Redis classes
        if (!$this->redis instanceof Predis\Client && version_compare(phpversion('redis'), '3.1.3', '<')) {
            throw new LogicException('RedisTagAwareAdapter requires php-redis 3.1.3 or higher, alternatively use predis/predis');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, ?int $lifetime, array $addTagData = [], array $delTagData = []): array
    {
        // serialize values
        if (!$serialized = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        // While pipeline isn't supported on RedisCluster, other setups will at least benefit from doing this in one op
        $results = $this->pipeline(static function () use ($serialized, $lifetime, $addTagData, $delTagData) {
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
                yield 'sAdd' => array_merge([$tagId], $ids);
            }

            foreach ($delTagData as $tagId => $ids) {
                yield 'sRem' => array_merge([$tagId], $ids);
            }
        });

        foreach ($results as $id => $result) {
            // Skip results of SADD/SREM operations, they'll be 1 or 0 depending on if set value already existed or not
            if (is_numeric($result)) {
                continue;
            }
            // setEx results
            if (true !== $result && (!$result instanceof Status || $result !== Status::get('OK'))) {
                $failed[] = $id;
            }
        }

        return $failed;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids, array $tagData = []): bool
    {
        if (!$ids) {
            return true;
        }

        $predisCluster = $this->redis instanceof \Predis\Client && $this->redis->getConnection() instanceof ClusterInterface;
        $this->pipeline(static function () use ($ids, $tagData, $predisCluster) {
            if ($predisCluster) {
                foreach ($ids as $id) {
                    yield 'del' => [$id];
                }
            } else {
                yield 'del' => $ids;
            }

            foreach ($tagData as $tagId => $idList) {
                yield 'sRem' => array_merge([$tagId], $idList);
            }
        })->rewind();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doInvalidate(array $tagIds): bool
    {
        if (!$this->redisServerSupportSPOP()) {
            return false;
        }

        // Pop all tag info at once to avoid race conditions
        $tagIdSets = $this->pipeline(static function () use ($tagIds) {
            foreach ($tagIds as $tagId) {
                // Client: Predis or PHP Redis 3.1.3+ (https://github.com/phpredis/phpredis/commit/d2e203a6)
                // Server: Redis 3.2 or higher (https://redis.io/commands/spop)
                yield 'sPop' => [$tagId, self::POP_MAX_LIMIT];
            }
        });

        // Flatten generator result from pipeline, ignore keys (tag ids)
        $ids = array_unique(array_merge(...iterator_to_array($tagIdSets, false)));

        // Delete cache in chunks to avoid overloading the connection
        foreach (array_chunk($ids, self::BULK_DELETE_LIMIT) as $chunkIds) {
            $this->doDelete($chunkIds);
        }

        return true;
    }

    private function redisServerSupportSPOP(): bool
    {
        if (null !== $this->redisServerSupportSPOP) {
            return $this->redisServerSupportSPOP;
        }

        foreach ($this->getHosts() as $host) {
            $info = $host->info('Server');
            $info = isset($info['Server']) ? $info['Server'] : $info;
            if (version_compare($info['redis_version'], '3.2', '<')) {
                CacheItem::log($this->logger, 'Redis server needs to be version 3.2 or higher, your Redis server was detected as '.$info['redis_version']);

                return $this->redisServerSupportSPOP = false;
            }
        }

        return $this->redisServerSupportSPOP = true;
    }
}
