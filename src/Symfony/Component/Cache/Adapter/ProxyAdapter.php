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
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ContractsTrait;
use Symfony\Component\Cache\Traits\ProxyTrait;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ProxyAdapter implements AdapterInterface, CacheInterface, PruneableInterface, ResettableInterface
{
    use ContractsTrait;
    use ProxyTrait;

    private string $namespace = '';
    private int $namespaceLen;
    private string $poolHash;
    private int $defaultLifetime;

    private static \Closure $createCacheItem;
    private static \Closure $setInnerItem;

    public function __construct(CacheItemPoolInterface $pool, string $namespace = '', int $defaultLifetime = 0)
    {
        $this->pool = $pool;
        $this->poolHash = spl_object_hash($pool);
        if ('' !== $namespace) {
            \assert('' !== CacheItem::validateKey($namespace));
            $this->namespace = $namespace;
        }
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
                $innerItem->expiresAt(($expiry ?? $item->expiry) ? \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $expiry ?? $item->expiry)) : null);
            },
            null,
            CacheItem::class
        );
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->pool instanceof CacheInterface) {
            return $this->doGet($this, $key, $callback, $beta, $metadata);
        }

        return $this->pool->get($this->getId($key), function ($innerItem, bool &$save) use ($key, $callback) {
            $item = (self::$createCacheItem)($key, $innerItem, $this->poolHash);
            $item->set($value = $callback($item, $save));
            (self::$setInnerItem)($innerItem, $item);

            return $value;
        }, $beta, $metadata);
    }

    public function getItem(mixed $key): CacheItem
    {
        $item = $this->pool->getItem($this->getId($key));

        return (self::$createCacheItem)($key, $item, $this->poolHash);
    }

    public function getItems(array $keys = []): iterable
    {
        if ($this->namespaceLen) {
            foreach ($keys as $i => $key) {
                $keys[$i] = $this->getId($key);
            }
        }

        return $this->generateItems($this->pool->getItems($keys));
    }

    public function hasItem(mixed $key): bool
    {
        return $this->pool->hasItem($this->getId($key));
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

    public function commit(): bool
    {
        return $this->pool->commit();
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

    private function generateItems(iterable $items): \Generator
    {
        $f = self::$createCacheItem;

        foreach ($items as $key => $item) {
            if ($this->namespaceLen) {
                $key = substr($key, $this->namespaceLen);
            }

            yield $key => $f($key, $item, $this->poolHash);
        }
    }

    private function getId(mixed $key): string
    {
        \assert('' !== CacheItem::validateKey($key));

        return $this->namespace.$key;
    }
}
