<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Cache\CacheException as Psr6CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\ProxyTrait;

/**
 * Turns a PSR-6 cache into a PSR-16 one.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr16Cache implements CacheInterface, PruneableInterface, ResettableInterface
{
    use ProxyTrait;

    private ?\Closure $createCacheItem = null;
    private ?CacheItem $cacheItemPrototype = null;
    private static \Closure $packCacheItem;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;

        if (!$pool instanceof AdapterInterface) {
            return;
        }
        $cacheItemPrototype = &$this->cacheItemPrototype;
        $createCacheItem = \Closure::bind(
            static function ($key, $value, $allowInt = false) use (&$cacheItemPrototype) {
                $item = clone $cacheItemPrototype;
                $item->poolHash = $item->innerItem = null;
                if ($allowInt && \is_int($key)) {
                    $item->key = (string) $key;
                } else {
                    \assert('' !== CacheItem::validateKey($key));
                    $item->key = $key;
                }
                $item->value = $value;
                $item->isHit = false;

                return $item;
            },
            null,
            CacheItem::class
        );
        $this->createCacheItem = function ($key, $value, $allowInt = false) use ($createCacheItem) {
            if (null === $this->cacheItemPrototype) {
                $this->get($allowInt && \is_int($key) ? (string) $key : $key);
            }
            $this->createCacheItem = $createCacheItem;

            return $createCacheItem($key, null, $allowInt)->set($value);
        };
        self::$packCacheItem ??= \Closure::bind(
            static function (CacheItem $item) {
                $item->newMetadata = $item->metadata;

                return $item->pack();
            },
            null,
            CacheItem::class
        );
    }

    public function get($key, $default = null): mixed
    {
        try {
            $item = $this->pool->getItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        if (null === $this->cacheItemPrototype) {
            $this->cacheItemPrototype = clone $item;
            $this->cacheItemPrototype->set(null);
        }

        return $item->isHit() ? $item->get() : $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        try {
            if (null !== $f = $this->createCacheItem) {
                $item = $f($key, $value);
            } else {
                $item = $this->pool->getItem($key)->set($value);
            }
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }

        return $this->pool->save($item);
    }

    public function delete($key): bool
    {
        try {
            return $this->pool->deleteItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    public function getMultiple($keys, $default = null): iterable
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(\sprintf('Cache keys must be array or Traversable, "%s" given.', get_debug_type($keys)));
        }

        try {
            $items = $this->pool->getItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $values = [];

        if (!$this->pool instanceof AdapterInterface) {
            foreach ($items as $key => $item) {
                $values[$key] = $item->isHit() ? $item->get() : $default;
            }

            return $values;
        }

        foreach ($items as $key => $item) {
            $values[$key] = $item->isHit() ? (self::$packCacheItem)($item) : $default;
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $valuesIsArray = \is_array($values);
        if (!$valuesIsArray && !$values instanceof \Traversable) {
            throw new InvalidArgumentException(\sprintf('Cache values must be array or Traversable, "%s" given.', get_debug_type($values)));
        }
        $items = [];

        try {
            if (null !== $f = $this->createCacheItem) {
                $valuesIsArray = false;
                foreach ($values as $key => $value) {
                    $items[$key] = $f($key, $value, true);
                }
            } elseif ($valuesIsArray) {
                $items = [];
                foreach ($values as $key => $value) {
                    $items[] = (string) $key;
                }
                $items = $this->pool->getItems($items);
            } else {
                foreach ($values as $key => $value) {
                    if (\is_int($key)) {
                        $key = (string) $key;
                    }
                    $items[$key] = $this->pool->getItem($key)->set($value);
                }
            }
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $ok = true;

        foreach ($items as $key => $item) {
            if ($valuesIsArray) {
                $item->set($values[$key]);
            }
            if (null !== $ttl) {
                $item->expiresAfter($ttl);
            }
            $ok = $this->pool->saveDeferred($item) && $ok;
        }

        return $this->pool->commit() && $ok;
    }

    public function deleteMultiple($keys): bool
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(\sprintf('Cache keys must be array or Traversable, "%s" given.', get_debug_type($keys)));
        }

        try {
            return $this->pool->deleteItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function has($key): bool
    {
        try {
            return $this->pool->hasItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
