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
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\ArrayTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ArrayAdapter implements AdapterInterface, LoggerAwareInterface, ResettableInterface
{
    use ArrayTrait;

    private $createCacheItem;
    private $defaultLifetime;

    /**
     * @param int  $defaultLifetime
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct($defaultLifetime = 0, $storeSerialized = true)
    {
        $this->defaultLifetime = $defaultLifetime;
        $this->storeSerialized = $storeSerialized;
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
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $isHit = $this->hasItem($key);
        try {
            if (!$isHit) {
                $this->values[$key] = $value = null;
            } elseif (!$this->storeSerialized) {
                $value = $this->values[$key];
            } elseif ('b:0;' === $value = $this->values[$key]) {
                $value = false;
            } elseif (false === $value = unserialize($value)) {
                $this->values[$key] = $value = null;
                $isHit = false;
            }
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to unserialize key "{key}"', ['key' => $key, 'exception' => $e]);
            $this->values[$key] = $value = null;
            $isHit = false;
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
            CacheItem::validateKey($key);
        }

        return $this->generateItems($keys, time(), $this->createCacheItem);
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
        $key = $item["\0*\0key"];
        $value = $item["\0*\0value"];
        $expiry = $item["\0*\0expiry"];

        if (0 === $expiry) {
            $expiry = \PHP_INT_MAX;
        }

        if (null !== $expiry && $expiry <= time()) {
            $this->deleteItem($key);

            return true;
        }
        if ($this->storeSerialized) {
            try {
                $value = serialize($value);
            } catch (\Exception $e) {
                $type = \is_object($value) ? \get_class($value) : \gettype($value);
                CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', ['key' => $key, 'type' => $type, 'exception' => $e]);

                return false;
            }
        }
        if (null === $expiry && 0 < $this->defaultLifetime) {
            $expiry = time() + $this->defaultLifetime;
        }

        $this->values[$key] = $value;
        $this->expiries[$key] = null !== $expiry ? $expiry : \PHP_INT_MAX;

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
}
