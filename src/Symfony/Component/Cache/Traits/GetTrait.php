<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * An implementation for CacheInterface that provides stampede protection via probabilistic early expiration.
 *
 * @see https://en.wikipedia.org/wiki/Cache_stampede
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait GetTrait
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null)
    {
        return $this->doGet($this, $key, $callback, $beta ?? 1.0);
    }

    private function doGet(CacheItemPoolInterface $pool, string $key, callable $callback, float $beta)
    {
        $t = 0;
        $item = $pool->getItem($key);
        $recompute = !$item->isHit() || INF === $beta;

        if ($item instanceof CacheItem && 0 < $beta) {
            if ($recompute) {
                $t = microtime(true);
            } else {
                $metadata = $item->getMetadata();
                $expiry = $metadata[CacheItem::METADATA_EXPIRY] ?? false;
                $ctime = $metadata[CacheItem::METADATA_CTIME] ?? false;

                if ($ctime && $expiry) {
                    $t = microtime(true);
                    $recompute = $expiry <= $t - $ctime / 1000 * $beta * log(random_int(1, PHP_INT_MAX) / PHP_INT_MAX);
                }
            }
            if ($recompute) {
                // force applying defaultLifetime to expiry
                $item->expiresAt(null);
            }
        }

        if (!$recompute) {
            return $item->get();
        }

        static $save = null;

        if (null === $save) {
            $save = \Closure::bind(
                function (CacheItemPoolInterface $pool, CacheItemInterface $item, $value, float $startTime) {
                    if ($item instanceof CacheItem && $startTime && $item->expiry > $endTime = microtime(true)) {
                        $item->newMetadata[CacheItem::METADATA_EXPIRY] = $item->expiry;
                        $item->newMetadata[CacheItem::METADATA_CTIME] = 1000 * (int) ($endTime - $startTime);
                    }
                    $pool->save($item->set($value));

                    return $value;
                },
                null,
                CacheItem::class
            );
        }

        return $save($pool, $item, $callback($item), $t);
    }
}
