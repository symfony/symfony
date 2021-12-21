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

use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class NullTagAwareAdapter extends NullAdapter implements TagAwareCacheInterface
{
    private static $createCacheItem;

    public function __construct()
    {
        self::$createCacheItem ?? self::$createCacheItem = \Closure::bind(
            static function ($key) {
                $item = new CacheItem();
                $item->key = $key;
                $item->isHit = false;
                $item->isTaggable = true;

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        $save = true;

        return $callback((self::$createCacheItem)($key), $save);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(mixed $key): CacheItem
    {
        return (self::$createCacheItem)($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        return $this->generateItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $tags): bool
    {
        return true;
    }

    private function generateItems(array $keys): \Generator
    {
        $f = self::$createCacheItem;

        foreach ($keys as $key) {
            yield $key => $f($key);
        }
    }
}
