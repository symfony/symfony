<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * An implementation of CacheInterface for PSR-6 CacheItemPoolInterface classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait CacheTrait
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null)
    {
        return $this->doGet($this, $key, $callback, $beta);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }

    private function doGet(CacheItemPoolInterface $pool, string $key, callable $callback, ?float $beta)
    {
        if (0 > $beta = $beta ?? 1.0) {
            throw new class(sprintf('Argument "$beta" provided to "%s::get()" must be a positive number, %f given.', \get_class($this), $beta)) extends \InvalidArgumentException implements InvalidArgumentException {
            };
        }

        $item = $pool->getItem($key);
        $recompute = !$item->isHit() || INF === $beta;

        if (!$recompute && $item instanceof ItemInterface) {
            $metadata = $item->getMetadata();
            $expiry = $metadata[ItemInterface::METADATA_EXPIRY] ?? false;
            $ctime = $metadata[ItemInterface::METADATA_CTIME] ?? false;

            if ($recompute = $ctime && $expiry && $expiry <= microtime(true) - $ctime / 1000 * $beta * log(random_int(1, PHP_INT_MAX) / PHP_INT_MAX)) {
                // force applying defaultLifetime to expiry
                $item->expiresAt(null);
            }
        }

        if ($recompute) {
            $pool->save($item->set($callback($item)));
        }

        return $item->get();
    }
}
