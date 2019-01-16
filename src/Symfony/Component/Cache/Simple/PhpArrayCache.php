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

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\PhpArrayTrait;

/**
 * Caches items at warm up time using a PHP array that is stored in shared memory by OPCache since PHP 7.0.
 * Warmed up items are read-only and run-time discovered items are cached using a fallback adapter.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PhpArrayCache implements CacheInterface, PruneableInterface, ResettableInterface
{
    use PhpArrayTrait;

    /**
     * @param string         $file         The PHP file were values are cached
     * @param CacheInterface $fallbackPool A pool to fallback on when an item is not hit
     */
    public function __construct(string $file, CacheInterface $fallbackPool)
    {
        $this->file = $file;
        $this->pool = $fallbackPool;
    }

    /**
     * This adapter takes advantage of how PHP stores arrays in its latest versions.
     *
     * @param string $file The PHP file were values are cached
     *
     * @return CacheInterface
     */
    public static function create($file, CacheInterface $fallbackPool)
    {
        // Shared memory is available in PHP 7.0+ with OPCache enabled
        if (filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            return new static($file, $fallbackPool);
        }

        return $fallbackPool;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }
        if (!isset($this->keys[$key])) {
            return $this->pool->get($key, $default);
        }
        $value = $this->values[$this->keys[$key]];

        if ('N;' === $value) {
            return null;
        }
        if ($value instanceof \Closure) {
            try {
                return $value();
            } catch (\Throwable $e) {
                return $default;
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!\is_array($keys)) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given', \is_object($keys) ? \get_class($keys) : \gettype($keys)));
        }
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
            }
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return $this->generateItems($keys, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return isset($this->keys[$key]) || $this->pool->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->keys[$key]) && $this->pool->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if (!\is_array($keys) && !$keys instanceof \Traversable) {
            throw new InvalidArgumentException(sprintf('Cache keys must be array or Traversable, "%s" given', \is_object($keys) ? \get_class($keys) : \gettype($keys)));
        }

        $deleted = true;
        $fallbackKeys = [];

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
            }

            if (isset($this->keys[$key])) {
                $deleted = false;
            } else {
                $fallbackKeys[] = $key;
            }
        }
        if (null === $this->values) {
            $this->initialize();
        }

        if ($fallbackKeys) {
            $deleted = $this->pool->deleteMultiple($fallbackKeys) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->keys[$key]) && $this->pool->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!\is_array($values) && !$values instanceof \Traversable) {
            throw new InvalidArgumentException(sprintf('Cache values must be array or Traversable, "%s" given', \is_object($values) ? \get_class($values) : \gettype($values)));
        }

        $saved = true;
        $fallbackValues = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key) && !\is_int($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
            }

            if (isset($this->keys[$key])) {
                $saved = false;
            } else {
                $fallbackValues[$key] = $value;
            }
        }

        if ($fallbackValues) {
            $saved = $this->pool->setMultiple($fallbackValues, $ttl) && $saved;
        }

        return $saved;
    }

    private function generateItems(array $keys, $default)
    {
        $fallbackKeys = [];

        foreach ($keys as $key) {
            if (isset($this->keys[$key])) {
                $value = $this->values[$this->keys[$key]];

                if ('N;' === $value) {
                    yield $key => null;
                } elseif ($value instanceof \Closure) {
                    try {
                        yield $key => $value();
                    } catch (\Throwable $e) {
                        yield $key => $default;
                    }
                } else {
                    yield $key => $value;
                }
            } else {
                $fallbackKeys[] = $key;
            }
        }

        if ($fallbackKeys) {
            yield from $this->pool->getMultiple($fallbackKeys, $default);
        }
    }
}
