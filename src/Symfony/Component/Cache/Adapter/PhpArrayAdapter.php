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
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ContractsTrait;
use Symfony\Component\Cache\Traits\PhpArrayTrait;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Caches items at warm up time using a PHP array that is stored in shared memory by OPCache since PHP 7.0.
 * Warmed up items are read-only and run-time discovered items are cached using a fallback adapter.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PhpArrayAdapter implements AdapterInterface, CacheInterface, PruneableInterface, ResettableInterface
{
    use ContractsTrait;
    use PhpArrayTrait;

    private $createCacheItem;

    /**
     * @param string           $file         The PHP file were values are cached
     * @param AdapterInterface $fallbackPool A pool to fallback on when an item is not hit
     */
    public function __construct(string $file, AdapterInterface $fallbackPool)
    {
        $this->file = $file;
        $this->pool = $fallbackPool;
        $this->createCacheItem = \Closure::bind(
            static function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * This adapter takes advantage of how PHP stores arrays in its latest versions.
     *
     * @param string                 $file         The PHP file were values are cached
     * @param CacheItemPoolInterface $fallbackPool A pool to fallback on when an item is not hit
     *
     * @return CacheItemPoolInterface
     */
    public static function create($file, CacheItemPoolInterface $fallbackPool)
    {
        if (!$fallbackPool instanceof AdapterInterface) {
            $fallbackPool = new ProxyAdapter($fallbackPool);
        }

        return new static($file, $fallbackPool);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
    {
        if (null === $this->values) {
            $this->initialize();
        }
        if (!isset($this->keys[$key])) {
            get_from_pool:
            if ($this->pool instanceof CacheInterface) {
                return $this->pool->get($key, $callback, $beta, $metadata);
            }

            return $this->doGet($this->pool, $key, $callback, $beta, $metadata);
        }
        $value = $this->values[$this->keys[$key]];

        if ('N;' === $value) {
            return null;
        }
        try {
            if ($value instanceof \Closure) {
                return $value();
            }
        } catch (\Throwable $e) {
            unset($this->keys[$key]);
            goto get_from_pool;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }
        if (!isset($this->keys[$key])) {
            return $this->pool->getItem($key);
        }

        $value = $this->values[$this->keys[$key]];
        $isHit = true;

        if ('N;' === $value) {
            $value = null;
        } elseif ($value instanceof \Closure) {
            try {
                $value = $value();
            } catch (\Throwable $e) {
                $value = null;
                $isHit = false;
            }
        }

        $f = $this->createCacheItem;

        return $f($key, $value, $isHit);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
            }
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return $this->generateItems($keys);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function hasItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return isset($this->keys[$key]) || $this->pool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function deleteItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', \is_object($key) ? \get_class($key) : \gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->keys[$key]) && $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function deleteItems(array $keys)
    {
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
            $deleted = $this->pool->deleteItems($fallbackKeys) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->keys[$item->getKey()]) && $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->keys[$item->getKey()]) && $this->pool->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function commit()
    {
        return $this->pool->commit();
    }

    private function generateItems(array $keys): \Generator
    {
        $f = $this->createCacheItem;
        $fallbackKeys = [];

        foreach ($keys as $key) {
            if (isset($this->keys[$key])) {
                $value = $this->values[$this->keys[$key]];

                if ('N;' === $value) {
                    yield $key => $f($key, null, true);
                } elseif ($value instanceof \Closure) {
                    try {
                        yield $key => $f($key, $value(), true);
                    } catch (\Throwable $e) {
                        yield $key => $f($key, null, false);
                    }
                } else {
                    yield $key => $f($key, $value, true);
                }
            } else {
                $fallbackKeys[] = $key;
            }
        }

        if ($fallbackKeys) {
            yield from $this->pool->getItems($fallbackKeys);
        }
    }

    /**
     * @throws \ReflectionException When $class is not found and is required
     *
     * @internal to be removed in Symfony 5.0
     */
    public static function throwOnRequiredClass($class)
    {
        $e = new \ReflectionException("Class $class does not exist");
        $trace = debug_backtrace();
        $autoloadFrame = [
            'function' => 'spl_autoload_call',
            'args' => [$class],
        ];

        if (\PHP_VERSION_ID >= 80000 && isset($trace[1])) {
            $callerFrame = $trace[1];
        } elseif (false !== $i = array_search($autoloadFrame, $trace, true)) {
            $callerFrame = $trace[++$i];
        } else {
            throw $e;
        }

        if (isset($callerFrame['function']) && !isset($callerFrame['class'])) {
            switch ($callerFrame['function']) {
                case 'get_class_methods':
                case 'get_class_vars':
                case 'get_parent_class':
                case 'is_a':
                case 'is_subclass_of':
                case 'class_exists':
                case 'class_implements':
                case 'class_parents':
                case 'trait_exists':
                case 'defined':
                case 'interface_exists':
                case 'method_exists':
                case 'property_exists':
                case 'is_callable':
                    return;
            }
        }

        throw $e;
    }
}
