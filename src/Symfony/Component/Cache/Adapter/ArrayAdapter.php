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
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ArrayAdapter implements AdapterInterface, CacheInterface, LoggerAwareInterface, ResettableInterface
{
    use ArrayTrait;

    private $createCacheItem;

    /**
     * @param int  $defaultLifetime
     * @param bool $storeSerialized Disabling serialization can lead to cache corruptions when storing mutable values but increases performance otherwise
     */
    public function __construct(int $defaultLifetime = 0, bool $storeSerialized = true)
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
            null,
            CacheItem::class
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
    {
        $item = $this->getItem($key);
        $metadata = $item->getMetadata();

        // ArrayAdapter works in memory, we don't care about stampede protection
        if (INF === $beta || !$item->isHit()) {
            $this->save($item->set($callback($item)));
        }

        return $item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if (!$isHit = $this->hasItem($key)) {
            $this->values[$key] = $value = null;
        } else {
            $value = $this->storeSerialized ? $this->unfreeze($key, $isHit) : $this->values[$key];
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
            if (!\is_string($key) || !isset($this->expiries[$key])) {
                CacheItem::validateKey($key);
            }
        }

        return $this->generateItems($keys, microtime(true), $this->createCacheItem);
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

        if (null !== $expiry && $expiry <= microtime(true)) {
            $this->deleteItem($key);

            return true;
        }
        if ($this->storeSerialized && null === $value = $this->freeze($value, $key)) {
            return false;
        }
        if (null === $expiry && 0 < $item["\0*\0defaultLifetime"]) {
            $expiry = microtime(true) + $item["\0*\0defaultLifetime"];
        }

        $this->values[$key] = $value;
        $this->expiries[$key] = null !== $expiry ? $expiry : PHP_INT_MAX;

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

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }
}
