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
use Symfony\Component\Cache\Traits\PhpArrayTrait;

/**
 * Caches items at warm up time using a PHP array that is stored in shared memory by OPCache since PHP 7.0.
 * Warmed up items are read-only and run-time discovered items are cached using a fallback adapter.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PhpArrayAdapter implements AdapterInterface, PruneableInterface, ResettableInterface
{
    use PhpArrayTrait;

    private $createCacheItem;

    /**
     * @param string           $file         The PHP file were values are cached
     * @param AdapterInterface $fallbackPool A pool to fallback on when an item is not hit
     */
    public function __construct($file, AdapterInterface $fallbackPool)
    {
        $this->file = $file;
        $this->pool = $fallbackPool;
        $this->zendDetectUnicode = ini_get('zend.detect_unicode');
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, $isHit) {
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
     * This adapter should only be used on PHP 7.0+ to take advantage of how PHP
     * stores arrays in its latest versions. This factory method decorates the given
     * fallback pool with this adapter only if the current PHP version is supported.
     *
     * @param string                 $file         The PHP file were values are cached
     * @param CacheItemPoolInterface $fallbackPool Fallback for old PHP versions or opcache disabled
     *
     * @return CacheItemPoolInterface
     */
    public static function create($file, CacheItemPoolInterface $fallbackPool)
    {
        // Shared memory is available in PHP 7.0+ with OPCache enabled and in HHVM
        if ((\PHP_VERSION_ID >= 70000 && ini_get('opcache.enable')) || defined('HHVM_VERSION')) {
            if (!$fallbackPool instanceof AdapterInterface) {
                $fallbackPool = new ProxyAdapter($fallbackPool);
            }

            return new static($file, $fallbackPool);
        }

        return $fallbackPool;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }
        if (!isset($this->values[$key])) {
            return $this->pool->getItem($key);
        }

        $value = $this->values[$key];
        $isHit = true;

        if ('N;' === $value) {
            $value = null;
        } elseif (\is_string($value) && isset($value[2]) && ':' === $value[1]) {
            try {
                $e = null;
                $value = unserialize($value);
            } catch (\Error $e) {
            } catch (\Exception $e) {
            }
            if (null !== $e) {
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
    public function getItems(array $keys = array())
    {
        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', is_object($key) ? get_class($key) : gettype($key)));
            }
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return $this->generateItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return isset($this->values[$key]) || $this->pool->hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->values[$key]) && $this->pool->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $deleted = true;
        $fallbackKeys = array();

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given.', is_object($key) ? get_class($key) : gettype($key)));
            }

            if (isset($this->values[$key])) {
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
     */
    public function save(CacheItemInterface $item)
    {
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->values[$item->getKey()]) && $this->pool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (null === $this->values) {
            $this->initialize();
        }

        return !isset($this->values[$item->getKey()]) && $this->pool->saveDeferred($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->pool->commit();
    }

    /**
     * @return \Generator
     */
    private function generateItems(array $keys)
    {
        $f = $this->createCacheItem;
        $fallbackKeys = array();

        foreach ($keys as $key) {
            if (isset($this->values[$key])) {
                $value = $this->values[$key];

                if ('N;' === $value) {
                    yield $key => $f($key, null, true);
                } elseif (\is_string($value) && isset($value[2]) && ':' === $value[1]) {
                    try {
                        yield $key => $f($key, unserialize($value), true);
                    } catch (\Error $e) {
                        yield $key => $f($key, null, false);
                    } catch (\Exception $e) {
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
            foreach ($this->pool->getItems($fallbackKeys) as $key => $item) {
                yield $key => $item;
            }
        }
    }

    /**
     * @throws \ReflectionException When $class is not found and is required
     *
     * @internal
     */
    public static function throwOnRequiredClass($class)
    {
        $e = new \ReflectionException("Class $class does not exist");
        $trace = $e->getTrace();
        $autoloadFrame = array(
            'function' => 'spl_autoload_call',
            'args' => array($class),
        );
        $i = 1 + array_search($autoloadFrame, $trace, true);

        if (isset($trace[$i]['function']) && !isset($trace[$i]['class'])) {
            switch ($trace[$i]['function']) {
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
