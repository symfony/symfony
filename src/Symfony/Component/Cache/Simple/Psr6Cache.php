<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheException as Psr6CacheException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr6Cache implements CacheInterface
{
    private $pool;
    private $createCacheItem;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;

        if ($pool instanceof AbstractAdapter) {
            $this->createCacheItem = \Closure::bind(
                function ($key, $value, $allowInt = false) {
                    if ($allowInt && is_int($key)) {
                        $key = (string) $key;
                    } else {
                        CacheItem::validateKey($key);
                    }
                    $f = $this->createCacheItem;

                    return $f($key, $value, false);
                },
                $pool,
                AbstractAdapter::class
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->pool->getItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
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

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->pool->deleteItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given', is_object($keys) ? get_class($keys) : gettype($keys)));
        }

        try {
            $items = $this->pool->getItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $values = array();

        foreach ($items as $key => $item) {
            $values[$key] = $item->isHit() ? $item->get() : $default;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $valuesIsArray = is_array($values);
        if (!$valuesIsArray && !$values instanceof \Traversable) {
            throw new InvalidArgumentException(sprintf('Cache values must be array or Traversable, "%s" given', is_object($values) ? get_class($values) : gettype($values)));
        }
        $items = array();

        try {
            if (null !== $f = $this->createCacheItem) {
                $valuesIsArray = false;
                foreach ($values as $key => $value) {
                    $items[$key] = $f($key, $value, true);
                }
            } elseif ($valuesIsArray) {
                $items = array();
                foreach ($values as $key => $value) {
                    $items[] = (string) $key;
                }
                $items = $this->pool->getItems($items);
            } else {
                foreach ($values as $key => $value) {
                    if (is_int($key)) {
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

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given', is_object($keys) ? get_class($keys) : gettype($keys)));
        }

        try {
            return $this->pool->deleteItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
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
