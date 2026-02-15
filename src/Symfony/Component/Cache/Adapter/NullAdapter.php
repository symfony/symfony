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
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class NullAdapter implements AdapterInterface, CacheInterface, NamespacedPoolInterface, TagAwareAdapterInterface
{
    private static \Closure $createCacheItem;

    public function __construct()
    {
        self::$createCacheItem ??= \Closure::bind(
            static function ($key) {
                $item = new CacheItem();
                $item->isTaggable = true;
                $item->key = $key;
                $item->isHit = false;

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $save = true;

        return $callback((self::$createCacheItem)($key), $save);
    }

    public function getItem(mixed $key): CacheItem
    {
        return (self::$createCacheItem)($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->generateItems($keys);
    }

    public function hasItem(mixed $key): bool
    {
        return false;
    }

    public function clear(string $prefix = ''): bool
    {
        return true;
    }

    public function deleteItem(mixed $key): bool
    {
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }

    public function withSubNamespace(string $namespace): static
    {
        return clone $this;
    }

    private function generateItems(array $keys): \Generator
    {
        $f = self::$createCacheItem;

        foreach ($keys as $key) {
            yield $key => $f($key);
        }
    }

    public function invalidateTags(array $tags): bool
    {
        return true;
    }
}
