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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait GetTrait
{
    private $callbackWrapper = array(LockRegistry::class, 'compute');

    /**
     * Wraps the callback passed to ->get() in a callable.
     *
     * @param callable(ItemInterface, callable, CacheInterface):mixed $callbackWrapper
     *
     * @return callable the previous callback wrapper
     */
    public function setCallbackWrapper(callable $callbackWrapper): callable
    {
        $previousWrapper = $this->callbackWrapper;
        $this->callbackWrapper = $callbackWrapper;

        return $previousWrapper;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null)
    {
        return $this->doGet($this, $key, $callback, $beta);
    }

    private function doGet(AdapterInterface $pool, string $key, callable $callback, ?float $beta)
    {
        if (0 > $beta = $beta ?? 1.0) {
            throw new InvalidArgumentException(sprintf('Argument "$beta" provided to "%s::get()" must be a positive number, %f given.', \get_class($this), $beta));
        }

        $t = 0;
        $item = $pool->getItem($key);
        $recompute = !$item->isHit() || INF === $beta;

        if (0 < $beta) {
            if ($recompute) {
                $t = microtime(true);
            } else {
                $metadata = $item->getMetadata();
                $expiry = $metadata[ItemInterface::METADATA_EXPIRY] ?? false;
                $ctime = $metadata[ItemInterface::METADATA_CTIME] ?? false;

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

        static $save;

        $save = $save ?? \Closure::bind(
            function (AdapterInterface $pool, ItemInterface $item, $value, float $startTime) {
                if ($startTime && $item->expiry > $endTime = microtime(true)) {
                    $item->newMetadata[ItemInterface::METADATA_EXPIRY] = $item->expiry;
                    $item->newMetadata[ItemInterface::METADATA_CTIME] = 1000 * (int) ($endTime - $startTime);
                }
                $pool->save($item->set($value));

                return $value;
            },
            null,
            CacheItem::class
        );

        // don't wrap nor save recursive calls
        if (null === $callbackWrapper = $this->callbackWrapper) {
            return $callback($item);
        }
        $this->callbackWrapper = null;

        try {
            return $save($pool, $item, $callbackWrapper($item, $callback, $pool), $t);
        } finally {
            $this->callbackWrapper = $callbackWrapper;
        }
    }
}
