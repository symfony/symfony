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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\RefreshableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ContractsTrait;
use Symfony\Component\Cache\Traits\ProxyTrait;
use Symfony\Component\Cache\Traits\RefreshableTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ProxyAdapter implements AdapterInterface, NamespacedPoolInterface, CacheInterface, PruneableInterface, RefreshableInterface, ResettableInterface
{
    use ContractsTrait;
    use ProxyTrait {
        reset as private proxyReset;
    }
    // handled here instead of being delegated, so that wrapping a foreign pool is enough
    use RefreshableTrait;

    private string $namespace = '';
    private int $namespaceLen;
    private string $poolHash;
    private int $defaultLifetime;

    private static \Closure $createCacheItem;
    private static \Closure $setInnerItem;

    public function __construct(CacheItemPoolInterface $pool, string $namespace = '', int $defaultLifetime = 0)
    {
        if ('' !== $namespace) {
            if ($pool instanceof NamespacedPoolInterface) {
                $pool = $pool->withSubNamespace($namespace);
                $this->namespace = $namespace = '';
            } else {
                \assert('' !== CacheItem::validateKey($namespace));
                $this->namespace = $namespace;
            }
        }
        $this->pool = $pool;
        $this->poolHash = spl_object_id($pool);
        $this->namespaceLen = \strlen($namespace);
        $this->defaultLifetime = $defaultLifetime;
        self::$createCacheItem ??= \Closure::bind(
            static function ($key, $innerItem, $poolHash) {
                $item = new CacheItem();
                $item->key = $key;

                if (null === $innerItem) {
                    return $item;
                }

                $item->value = $innerItem->get();
                $item->isHit = $innerItem->isHit();
                $item->innerItem = $innerItem;
                $item->poolHash = $poolHash;

                if (!$item->unpack() && $innerItem instanceof CacheItem) {
                    $item->metadata = $innerItem->metadata;
                }
                $innerItem->set(null);

                return $item;
            },
            null,
            CacheItem::class
        );
        self::$setInnerItem ??= \Closure::bind(
            static function (CacheItemInterface $innerItem, CacheItem $item, $expiry = null) {
                $innerItem->set($item->pack());
                $innerItem->expiresAt(($expiry ?? $item->expiry) ? \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $expiry ?? $item->expiry)) : null);
            },
            null,
            CacheItem::class
        );
    }

    /**
     * @param-immediately-invoked-callable $callback
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->pool instanceof CacheInterface) {
            return $this->doGet($this, $key, $callback, $beta, $metadata);
        }

        $id = $this->getId($key);

        if ($this->refreshing && $this->shouldRefresh($id)) {
            $beta = \INF;
        }

        return $this->pool->get($id, function ($innerItem, bool &$save) use ($key, $callback) {
            $item = (self::$createCacheItem)($key, $innerItem, $this->poolHash);
            $item->set($value = $callback($item, $save));
            (self::$setInnerItem)($innerItem, $item);

            return $value;
        }, $beta, $metadata);
    }

    public function getItem(mixed $key): CacheItem
    {
        $id = $this->getId($key);

        if ($this->refreshing && $this->shouldRefresh($id)) {
            return (self::$createCacheItem)($key, null, $this->poolHash);
        }

        return (self::$createCacheItem)($key, $this->pool->getItem($id), $this->poolHash);
    }

    public function getItems(array $keys = []): iterable
    {
        $refreshing = [];

        if ($this->refreshing || $this->namespaceLen) {
            foreach ($keys as $i => $key) {
                $id = $this->getId($key);

                if ($this->refreshing && $this->shouldRefresh($id)) {
                    $refreshing[$key] = true;
                }

                if ($this->namespaceLen) {
                    $keys[$i] = $id;
                }
            }
        }

        return $this->generateItems($this->pool->getItems($keys), $refreshing);
    }

    public function hasItem(mixed $key): bool
    {
        $id = $this->getId($key);

        if ($this->refreshing && !isset($this->refreshed[$id])) {
            return false;
        }

        return $this->pool->hasItem($id);
    }

    public function clear(string $prefix = ''): bool
    {
        if ($this->pool instanceof AdapterInterface) {
            return $this->pool->clear($this->namespace.$prefix);
        }

        return $this->pool->clear();
    }

    public function deleteItem(mixed $key): bool
    {
        return $this->pool->deleteItem($this->getId($key));
    }

    public function deleteItems(array $keys): bool
    {
        if ($this->namespaceLen) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->getId($key);
            }
        }

        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->doSave($item, __FUNCTION__);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->doSave($item, __FUNCTION__);
    }

    public function reset(): void
    {
        $this->enableRefresh(false);
        $this->proxyReset();
    }

    public function commit(): bool
    {
        return $this->pool->commit();
    }

    public function withSubNamespace(string $namespace): static
    {
        $clone = clone $this;

        if ($clone->pool instanceof NamespacedPoolInterface) {
            $clone->pool = $clone->pool->withSubNamespace($namespace);
        } else {
            $clone->namespace .= CacheItem::validateKey($namespace);
            $clone->namespaceLen = \strlen($clone->namespace);
        }

        return $clone;
    }

    private function doSave(CacheItemInterface $item, string $method): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $castItem = (array) $item;

        if (null === $castItem["\0*\0expiry"] && 0 < $this->defaultLifetime) {
            $castItem["\0*\0expiry"] = microtime(true) + $this->defaultLifetime;
        }

        if ($castItem["\0*\0poolHash"] === $this->poolHash && $castItem["\0*\0innerItem"]) {
            $innerItem = $castItem["\0*\0innerItem"];
        } elseif ($this->pool instanceof AdapterInterface) {
            // this is an optimization specific for AdapterInterface implementations
            // so we can save a round-trip to the backend by just creating a new item
            $innerItem = (self::$createCacheItem)($this->namespace.$castItem["\0*\0key"], null, $this->poolHash);
        } else {
            $innerItem = $this->pool->getItem($this->namespace.$castItem["\0*\0key"]);
        }

        (self::$setInnerItem)($innerItem, $item, $castItem["\0*\0expiry"]);

        return $this->pool->$method($innerItem);
    }

    private function generateItems(iterable $items, array $refreshing): \Generator
    {
        $f = self::$createCacheItem;

        foreach ($items as $key => $item) {
            if ($this->namespaceLen) {
                $key = substr($key, $this->namespaceLen);
            }

            yield $key => $f($key, isset($refreshing[$key]) ? null : $item, $this->poolHash);
        }
    }

    private function getId(mixed $key): string
    {
        \assert('' !== CacheItem::validateKey($key));

        return $this->namespace.$key;
    }
}
