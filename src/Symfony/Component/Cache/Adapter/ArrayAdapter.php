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
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * An in-memory cache storage.
 *
 * Acts as a least-recently-used (LRU) storage when configured with a maximum number of items.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ArrayAdapter implements AdapterInterface, CacheInterface, NamespacedPoolInterface, LoggerAwareInterface, ResettableInterface
{
    use LoggerAwareTrait;

    private array $values = [];
    private array $tags = [];
    private array $expiries = [];
    private array $subPools = [];

    private static \Closure $createCacheItem;

    /**
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(
        private int $defaultLifetime = 0,
        private bool $storeSerialized = true,
        private float $maxLifetime = 0,
        private int $maxItems = 0,
        private ?ClockInterface $clock = null,
    ) {
        if (0 > $maxLifetime) {
            throw new InvalidArgumentException(\sprintf('Argument $maxLifetime must be positive, %F passed.', $maxLifetime));
        }

        if (0 > $maxItems) {
            throw new InvalidArgumentException(\sprintf('Argument $maxItems must be a positive integer, %d passed.', $maxItems));
        }

        self::$createCacheItem ??= \Closure::bind(
            static function ($key, $value, $isHit, $tags) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                if (null !== $tags) {
                    $item->metadata[CacheItem::METADATA_TAGS] = $tags;
                }

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $item = $this->getItem($key);
        $metadata = $item->getMetadata();

        // ArrayAdapter works in memory, we don't care about stampede protection
        if (\INF === $beta || !$item->isHit()) {
            $save = true;
            $item->set($callback($item, $save));
            if ($save) {
                $this->save($item);
            }
        }

        return $item->get();
    }

    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }

    public function hasItem(mixed $key): bool
    {
        if (\is_string($key) && isset($this->expiries[$key]) && $this->expiries[$key] > $this->getCurrentTime()) {
            if ($this->maxItems) {
                // Move the item last in the storage
                $value = $this->values[$key];
                unset($this->values[$key]);
                $this->values[$key] = $value;
            }

            return true;
        }
        \assert('' !== CacheItem::validateKey($key));

        return isset($this->expiries[$key]) && !$this->deleteItem($key);
    }

    public function getItem(mixed $key): CacheItem
    {
        if (!$isHit = $this->hasItem($key)) {
            $value = null;

            if (!$this->maxItems) {
                // Track misses in non-LRU mode only
                $this->values[$key] = null;
            }
        } else {
            $value = $this->storeSerialized ? $this->unfreeze($key, $isHit) : $this->values[$key];
        }

        return (self::$createCacheItem)($key, $value, $isHit, $this->tags[$key] ?? null);
    }

    public function getItems(array $keys = []): iterable
    {
        \assert(self::validateKeys($keys));

        return $this->generateItems($keys, $this->getCurrentTime(), self::$createCacheItem);
    }

    public function deleteItem(mixed $key): bool
    {
        \assert('' !== CacheItem::validateKey($key));
        unset($this->values[$key], $this->tags[$key], $this->expiries[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $item = (array) $item;
        $key = $item["\0*\0key"];
        $value = $item["\0*\0value"];
        $expiry = $item["\0*\0expiry"];

        $now = $this->getCurrentTime();

        if (null !== $expiry) {
            if (!$expiry) {
                $expiry = \PHP_INT_MAX;
            } elseif ($expiry <= $now) {
                $this->deleteItem($key);

                return true;
            }
        }
        if ($this->storeSerialized && null === $value = $this->freeze($value, $key)) {
            return false;
        }
        if (null === $expiry && 0 < $this->defaultLifetime) {
            $expiry = $this->defaultLifetime;
            $expiry = $now + ($expiry > ($this->maxLifetime ?: $expiry) ? $this->maxLifetime : $expiry);
        } elseif ($this->maxLifetime && (null === $expiry || $expiry > $now + $this->maxLifetime)) {
            $expiry = $now + $this->maxLifetime;
        }

        if ($this->maxItems) {
            unset($this->values[$key], $this->tags[$key]);

            // Iterate items and vacuum expired ones while we are at it
            foreach ($this->values as $k => $v) {
                if ($this->expiries[$k] > $now && \count($this->values) < $this->maxItems) {
                    break;
                }

                unset($this->values[$k], $this->tags[$k], $this->expiries[$k]);
            }
        }

        $this->values[$key] = $value;
        $this->expiries[$key] = $expiry ?? \PHP_INT_MAX;

        if (null === $this->tags[$key] = $item["\0*\0newMetadata"][CacheItem::METADATA_TAGS] ?? null) {
            unset($this->tags[$key]);
        }

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    public function clear(string $prefix = ''): bool
    {
        if ('' !== $prefix) {
            $now = $this->getCurrentTime();

            foreach ($this->values as $key => $value) {
                if (!isset($this->expiries[$key]) || $this->expiries[$key] <= $now || str_starts_with($key, $prefix)) {
                    unset($this->values[$key], $this->tags[$key], $this->expiries[$key]);
                }
            }

            return true;
        }

        foreach ($this->subPools as $pool) {
            $pool->clear();
        }

        $this->subPools = $this->values = $this->tags = $this->expiries = [];

        return true;
    }

    public function withSubNamespace(string $namespace): static
    {
        CacheItem::validateKey($namespace);

        $subPools = $this->subPools;

        if (isset($subPools[$namespace])) {
            return $subPools[$namespace];
        }

        $this->subPools = [];
        $clone = clone $this;
        $clone->clear();

        $subPools[$namespace] = $clone;
        $this->subPools = $subPools;

        return $clone;
    }

    /**
     * Returns all cached values, with cache miss as null.
     */
    public function getValues(): array
    {
        if (!$this->storeSerialized) {
            return $this->values;
        }

        $values = $this->values;
        foreach ($values as $k => $v) {
            if (null === $v || 'N;' === $v) {
                continue;
            }
            if (!\is_string($v) || !isset($v[2]) || ':' !== $v[1]) {
                $values[$k] = serialize($v);
            }
        }

        return $values;
    }

    public function reset(): void
    {
        $this->clear();
    }

    public function __clone()
    {
        foreach ($this->subPools as $i => $pool) {
            $this->subPools[$i] = clone $pool;
        }
    }

    private function generateItems(array $keys, float $now, \Closure $f): \Generator
    {
        foreach ($keys as $i => $key) {
            if (!$isHit = isset($this->expiries[$key]) && ($this->expiries[$key] > $now || !$this->deleteItem($key))) {
                $value = null;

                if (!$this->maxItems) {
                    // Track misses in non-LRU mode only
                    $this->values[$key] = null;
                }
            } else {
                if ($this->maxItems) {
                    // Move the item last in the storage
                    $value = $this->values[$key];
                    unset($this->values[$key]);
                    $this->values[$key] = $value;
                }

                $value = $this->storeSerialized ? $this->unfreeze($key, $isHit) : $this->values[$key];
            }
            unset($keys[$i]);

            yield $key => $f($key, $value, $isHit, $this->tags[$key] ?? null);
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }

    private function freeze($value, string $key): string|int|float|bool|array|\UnitEnum|null
    {
        if (null === $value) {
            return 'N;';
        }
        if (\is_string($value)) {
            // Serialize strings if they could be confused with serialized objects or arrays
            if ('N;' === $value || (isset($value[2]) && ':' === $value[1])) {
                return serialize($value);
            }
        } elseif (!\is_scalar($value)) {
            try {
                $serialized = serialize($value);
            } catch (\Exception $e) {
                if (!isset($this->expiries[$key])) {
                    unset($this->values[$key]);
                }
                $type = get_debug_type($value);
                $message = \sprintf('Failed to save key "{key}" of type %s: %s', $type, $e->getMessage());
                CacheItem::log($this->logger, $message, ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);

                return null;
            }
            // Keep value serialized if it contains any objects or any internal references
            if ('C' === $serialized[0] || 'O' === $serialized[0] || preg_match('/;[OCRr]:[1-9]/', $serialized)) {
                return $serialized;
            }
        }

        return $value;
    }

    private function unfreeze(string $key, bool &$isHit): mixed
    {
        if ('N;' === $value = $this->values[$key]) {
            return null;
        }
        if (\is_string($value) && isset($value[2]) && ':' === $value[1]) {
            try {
                $value = unserialize($value);
            } catch (\Exception $e) {
                CacheItem::log($this->logger, 'Failed to unserialize key "{key}": '.$e->getMessage(), ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]);
                $value = false;
            }
            if (false === $value) {
                $value = null;
                $isHit = false;

                if (!$this->maxItems) {
                    $this->values[$key] = null;
                }
            }
        }

        return $value;
    }

    private function validateKeys(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!\is_string($key) || !isset($this->expiries[$key])) {
                CacheItem::validateKey($key);
            }
        }

        return true;
    }

    private function getCurrentTime(): float
    {
        return $this->clock?->now()->format('U.u') ?? microtime(true);
    }
}
