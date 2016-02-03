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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ArrayAdapter implements CacheItemPoolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private $storeSerialized;
    private $values = array();
    private $expiries = array();
    private $createCacheItem;

    /**
     * @param int  $defaultLifetime
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise.
     */
    public function __construct($defaultLifetime = 0, $storeSerialized = true)
    {
        $this->storeSerialized = $storeSerialized;
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, $isHit) use ($defaultLifetime) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                $item->defaultLifetime = $defaultLifetime;

                return $item;
            },
            $this,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if (!$isHit = $this->hasItem($key)) {
            $value = null;
        } elseif ($this->storeSerialized) {
            $value = unserialize($this->values[$key]);
        } else {
            $value = $this->values[$key];
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
            $this->validateKey($key);
        }

        return $this->generateItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        return isset($this->expiries[$this->validateKey($key)]) && ($this->expiries[$key] >= time() || !$this->deleteItem($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->values = $this->expiries = array();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        unset($this->values[$this->validateKey($key)], $this->expiries[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $item = (array) $item;
        $key = $item[CacheItem::CAST_PREFIX.'key'];
        $value = $item[CacheItem::CAST_PREFIX.'value'];
        $lifetime = $item[CacheItem::CAST_PREFIX.'lifetime'];

        if (0 > $lifetime) {
            return true;
        }
        if ($this->storeSerialized) {
            try {
                $value = serialize($value);
            } catch (\Exception $e) {
                $type = is_object($value) ? get_class($value) : gettype($value);
                CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', array('key' => $key, 'type' => $type, 'exception' => $e));

                return false;
            }
        }

        $this->values[$key] = $value;
        $this->expiries[$key] = $lifetime ? $lifetime + time() : PHP_INT_MAX;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        return $this->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return true;
    }

    private function validateKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (!isset($key[0])) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }
        if (isset($key[strcspn($key, '{}()/\@:')])) {
            throw new InvalidArgumentException('Cache key contains reserved characters {}()/\@:');
        }

        return $key;
    }

    private function generateItems(array $keys)
    {
        $f = $this->createCacheItem;

        foreach ($keys as $key) {
            if (!$isHit = isset($this->expiries[$key]) && ($this->expiries[$key] >= time() || !$this->deleteItem($key))) {
                $value = null;
            } elseif ($this->storeSerialized) {
                $value = unserialize($this->values[$key]);
            } else {
                $value = $this->values[$key];
            }

            yield $key => $f($key, $value, $isHit);
        }
    }
}
