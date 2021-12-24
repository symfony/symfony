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

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CacheTrait;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait ContractsTrait
{
    use CacheTrait {
        doGet as private contractsGet;
    }

    private $callbackWrapper;
    private $computing = [];

    /**
     * Wraps the callback passed to ->get() in a callable.
     *
     * @return callable the previous callback wrapper
     */
    public function setCallbackWrapper(?callable $callbackWrapper): callable
    {
        if (!isset($this->callbackWrapper)) {
            $this->callbackWrapper = \Closure::fromCallable([LockRegistry::class, 'compute']);

            if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
                $this->setCallbackWrapper(null);
            }
        }

        $previousWrapper = $this->callbackWrapper;
        $this->callbackWrapper = $callbackWrapper ?? static function (callable $callback, ItemInterface $item, bool &$save, CacheInterface $pool, \Closure $setMetadata, ?LoggerInterface $logger) {
            return $callback($item, $save);
        };

        return $previousWrapper;
    }

    private function doGet(AdapterInterface $pool, string $key, callable $callback, ?float $beta, array &$metadata = null)
    {
        if (0 > $beta = $beta ?? 1.0) {
            throw new InvalidArgumentException(sprintf('Argument "$beta" provided to "%s::get()" must be a positive number, %f given.', static::class, $beta));
        }

        static $setMetadata;

        $setMetadata ?? $setMetadata = \Closure::bind(
            static function (CacheItem $item, float $startTime, ?array &$metadata) {
                if ($item->expiry > $endTime = microtime(true)) {
                    $item->newMetadata[CacheItem::METADATA_EXPIRY] = $metadata[CacheItem::METADATA_EXPIRY] = $item->expiry;
                    $item->newMetadata[CacheItem::METADATA_CTIME] = $metadata[CacheItem::METADATA_CTIME] = (int) ceil(1000 * ($endTime - $startTime));
                } else {
                    unset($metadata[CacheItem::METADATA_EXPIRY], $metadata[CacheItem::METADATA_CTIME]);
                }
            },
            null,
            CacheItem::class
        );

        return $this->contractsGet($pool, $key, function (CacheItem $item, bool &$save) use ($pool, $callback, $setMetadata, &$metadata, $key) {
            // don't wrap nor save recursive calls
            if (isset($this->computing[$key])) {
                $value = $callback($item, $save);
                $save = false;

                return $value;
            }

            $this->computing[$key] = $key;
            $startTime = microtime(true);

            if (!isset($this->callbackWrapper)) {
                $this->setCallbackWrapper($this->setCallbackWrapper(null));
            }

            try {
                $value = ($this->callbackWrapper)($callback, $item, $save, $pool, function (CacheItem $item) use ($setMetadata, $startTime, &$metadata) {
                    $setMetadata($item, $startTime, $metadata);
                }, $this->logger ?? null);
                $setMetadata($item, $startTime, $metadata);

                return $value;
            } finally {
                unset($this->computing[$key]);
            }
        }, $beta, $metadata, $this->logger ?? null);
    }
}
