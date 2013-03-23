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

use Symfony\Component\Cache\Driver\DriverInterface;
use Symfony\Component\Cache\Driver\BatchDriverInterface;
use Symfony\Component\Cache\Item\CacheItemInterface;
use Symfony\Component\Cache\Item\CacheItem;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * This is our cache proxy
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
class CacheDriver
{
    /**
     * Our cache driver
     *
     * @var DriverInterface|BatchDriverInterface
     */
    private $driver;

    /**
     * This should store the logger interface
     *
     * @var null|LoggerInterface
     */
    private $logger = null;

    /**
     * Cache profiler
     *
     * @var null|CacheProfiler
     */
    private $profiler = null;

    /**
     * Name of the cache instance
     *
     * @var string
     */
    private $name;

    /**
     * Type of the cache instance
     *
     * @var string
     */
    private $type;

    /**
     * The default TTL (in seconds)
     *
     * @var int
     */
    private $defaultTtl = 600;

    /**
     * Create our proxy so that we can use objects to our drivers and have other cool things like logging and profiling
     *
     * @param DriverInterface   $driver
     * @param string            $name
     * @param string            $type
     */
    public function __construct(DriverInterface $driver, $name, $type)
    {
        $this->driver = $driver;
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Set the cache logger
     *
     * @param LoggerInterface $logger
     *
     * @return CacheDriver
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set the profiler
     *
     * @param CacheProfiler $profiler
     *
     * @return CacheDriver
     */
    public function setProfiler(CacheProfiler $profiler)
    {
        $this->profiler = $profiler;

        return $this;
    }

    /**
     * Return the profiler
     *
     * @return CacheProfiler
     */
    public function getProfiler()
    {
        return $this->profiler;
    }

    /**
     * Get the default TTL of the instance
     *
     * @return int
     */
    public function getDefaultTtl()
    {
        return $this->defaultTtl;
    }

    /**
     * Set the default TTL of the instance
     *
     * @param $defaultTtl
     *
     * @return CacheDriver
     */
    public function setDefaultTtl($defaultTtl)
    {
        $this->defaultTtl = $defaultTtl;

        return $this;
    }

    /**
     * Get cache entry
     *
     * @param string|CacheItemInterface $key
     * @param boolean|null $exists
     *
     * @return CacheItemInterface
     */
    public function get($key, &$exists = null)
    {
        $key = $this->getKeyValue($key);

        if (null !== $this->profiler) {
            $this->profiler->start($this->type, $this->name, 'get', $key);
        }

        $value = $this->driver->get($key, $exists);

        if (null !== $this->profiler) {
            $this->profiler->stop($this->type, $this->name, 'get', $key, $exists);
        }

        return $this->ensureCacheItem($key, $value);
    }

    /**
     * Check if a cache entry exists
     *
     * @param string|CacheItemInterface $key
     *
     * @return boolean
     */
    public function exists($key)
    {
        $key = $this->getKeyValue($key);

        if ($this->logger) {
            $this->logger->debug('Checking cache for ' . $key);
        }

        if (null !== $this->profiler) {
            $this->profiler->start($this->type, $this->name, 'exists', $key);
        }

        $result = $this->driver->exists($key);

        if (null !== $this->profiler) {
            $this->profiler->stop($this->type, $this->name, 'exists', $key, $result);
        }

        return $result;
    }

    /**
     * Set a single cache entry
     *
     * @param CacheItemInterface $cacheItem
     *
     * @return boolean Result of the operation
     */
    public function set(CacheItemInterface $cacheItem)
    {
        $key = $cacheItem->getKey();

        if ($this->logger && strpos($key, '__lk_') === false) {
            $this->logger->debug('Setting cache ' . $key);
        }

        if ($this->driver->hasSerializationSupport()) {
            $cacheValue = $cacheItem;
        } else {
            $cacheValue = serialize($cacheItem);
        }

        if (null !== $this->profiler) {
            $this->profiler->start($this->type, $this->name, 'set', $key);
        }

        $result = $this->driver->set($key, $cacheValue, $cacheItem->getTtl());

        if (null !== $this->profiler) {
            $this->profiler->stop($this->type, $this->name, 'set', $key, $result);
        }

        return $result;
    }

    /**
     * Remove a single cache entry
     *
     * @param string|CacheItemInterface $key
     *
     * @return boolean Result of the operation
     */
    public function remove($key)
    {
        $key = $this->getKeyValue($key);

        if ($this->logger && strpos($key, '__lk_') === false) {
            $this->logger->debug('Deleting from cache ' . $key);
        }

        if (null !== $this->profiler) {
            $this->profiler->start($this->type, $this->name, 'delete', $key);
        }

        $result = $this->driver->remove($key);

        if (null !== $this->profiler) {
            $this->profiler->stop($this->type, $this->name, 'delete', $key, $result);
        }

        return $result;
    }

    /**
     * Set multiple keys in the cache
     * If $ttl is not passed then the default TTL for this driver will be used
     *
     * @param string[]|CacheItemInterface[]|mixed[] $items
     * @param null|int $ttl
     */
    public function setMultiple(array $items, $ttl = null)
    {
        // Check if we have serialization support
        $hasSerializationSupport = $this->driver->hasSerializationSupport();

        if (null == $ttl) {
            $ttl = $this->getDefaultTtl();
        }

        // Ensure all items are in the correct format
        array_walk($items, function (&$value, $key) use ($ttl, $hasSerializationSupport) {
            if (!$value instanceof CacheItemInterface) {
                $value = new CacheItem($key, $value, $ttl);
            } else {
                $value->setTtl($ttl);
            }

            if (!$hasSerializationSupport) {
                $value = serialize($value);
            }
        });

        if ($this->driver instanceof Driver\BatchDriverInterface) {
            if ($this->logger) {
                $this->logger->debug('Setting cache ' . implode(', ', array_keys($items)));
            }

            if (null !== $this->profiler) {
                $this->profiler->start($this->type, $this->name, 'setMulti');
            }

            $this->driver->setMultiple($items, $ttl);

            if (null !== $this->profiler) {
                $this->profiler->stop($this->type, $this->name, 'setMulti', '', true);
            }
        } else {
            foreach ($items as $key => $value) {
                $this->driver->set($key, $value, $ttl);
            }
        }
    }

    /**
     * Get multiple keys the cache
     *
     * @param string[]|CacheItemInterface[]|mixed[] $keys
     *
     * @return CacheItemInterface[]
     */
    public function getMultiple($keys)
    {
        $keys = $this->convertKeysToString($keys);

        if ($this->logger) {
            $this->logger->debug('Getting from cache ' . implode(', ', $keys));
        }

        if ($this->driver instanceof Driver\BatchDriverInterface) {
            if (null !== $this->profiler) {
                $this->profiler->start($this->type, $this->name, 'getMulti');
            }

            $result = $this->driver->getMultiple($keys);

            if (null !== $this->profiler) {
                $this->profiler->stop($this->type, $this->name, 'getMulti', '', true);
            }
        } else {
            $result = array();
            foreach ($keys as $key) {
                $result[$key] = $this->driver->get($key);
            }
        }

        $that = $this;
        array_walk($result, function (&$value, $key) use ($that) {
            $value = $that->ensureCacheItem($key, $value);
        });

        return $result;
    }

    /**
     * Remove multiple keys from the cache
     *
     * @param string[]|CacheItemInterface[]|mixed[] $keys
     */
    public function removeMultiple($keys)
    {
        $keys = $this->convertKeysToString($keys);

        if ($this->logger) {
            $this->logger->debug('Deleting from cache ' . implode(', ', $keys));
        }

        $results = array();

        if ($this->driver instanceof Driver\BatchDriverInterface) {
            if (null !== $this->profiler) {
                $this->profiler->start($this->type, $this->name, 'deleteMulti', '');
            }

            $results = $this->driver->removeMultiple($keys);

            if (null !== $this->profiler) {
                $this->profiler->stop($this->type, $this->name, 'deleteMulti', '', true);
            }
        } else {
            foreach ($keys as $key) {
                $results[$key] = $this->driver->remove($key);
            }
        }

        return $results;
    }

    /**
     * Check if multiple keys exists in the cache
     *
     * @param string[]|CacheItemInterface[]|mixed[] $keys
     *
     * @return boolean[]
     */
    public function existsMultiple($keys)
    {
        $keys = $this->convertKeysToString($keys);

        if ($this->logger) {
             $this->logger->debug('Checking cache for ' . implode(', ', $keys));
        }

        if ($this->driver instanceof Driver\BatchDriverInterface) {
            if (null !== $this->profiler) {
                $this->profiler->start($this->type, $this->name, 'existsMulti', '');
            }

            $result = $this->driver->existsMultiple($keys);

            if (null !== $this->profiler) {
                $this->profiler->stop($this->type, $this->name, 'existsMulti', '', true);
            }
        } else {
            $result = array();
            foreach ($keys as $key) {
                $result[] = $this->driver->exists($key);
            }
        }

        return $result;
    }

    /**
     * Convert the values of an array to strings only
     *
     * @param string[]|CacheItemInterface[]|mixed[] $keys
     *
     * @return string[]
     */
    private function convertKeysToString($keys)
    {
        return array_map(function ($value) {
            if ($value instanceof CacheItemInterface) {
                $key = $value->getKey();
            } else {
                $key = (string) $value;
            }

            return $key;
        }, $keys);
    }

    /**
     * Ensure that we receive a key string from the item
     *
     * @param mixed $item
     *
     * @return string
     */
    private function getKeyValue($item)
    {
        if ($item instanceof CacheItemInterface) {
            return $item->getKey();
        } else {
            return (string) $item;
        }
    }

    /**
     * Make sure that the value passed is a object
     *
     * @param string $key
     * @param mixed $value
     *
     * @return CacheItemInterface
     */
    private function ensureCacheItem($key, $value)
    {
        // We have our item as expected
        if ($value instanceof CacheItemInterface) {
            return $value;
        }

        // We don't have a string value
        if (!is_string($value)) {
            return new CacheItem($key, $value, 0);
        }

        // We have a string value on our hands but we are not sure it can be unserialized
        if (false === $val = @unserialize($value)) {
            return new CacheItem($key, $val, 0);
        }

        // Everything else failed???
        return new CacheItem($key, $value, 0);
    }
}
