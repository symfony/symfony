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
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\CacheItem;

/**
 * @author Michaël Garrez <michael.garrez@gmail.com>
 * @author Sébastien Breysse <contact@rezouce.net>
 */
class MemcachedAdapter implements AdapterInterface
{
    private $namespaceKey;
    private $getCacheItemAsArray;
    private $deferredItems = array();
    private $changeIsHit;
    private $namespace;
    private $createCacheItem;
    private $client;

    /**
     * @param \Memcached $client
     * @param string     $namespace
     * @param int        $defaultLifetime
     */
    public function __construct(\Memcached $client, $namespace = '', $defaultLifetime = 0)
    {
        $this->client = $client;
        $this->namespaceKey = 'symfony_cache_namespace_key_'.$namespace;
        $this->namespace = $this->client->get($this->namespaceKey);

        if (false === $this->namespace) {
            $this->namespace = $this->client->get($this->namespaceKey);

            $this->client->set($this->namespaceKey, time());
        }

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

        $this->getCacheItemAsArray = \Closure::bind(
            function (CacheItem $item) use ($defaultLifetime) {
                return array(
                    'key' => $item->key,
                    'value' => $item->value,
                    'expiry' => $item->expiry !== null ? $item->expiry : $defaultLifetime,
                );
            },
            null,
            CacheItem::class
        );

        $this->changeIsHit = \Closure::bind(
            function ($item, $isHit) {
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );
    }

    public function __destruct()
    {
        if ($this->deferredItems) {
            $this->commit();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if ($this->deferredItems) {
            $this->commit();
        }

        $isHit = $this->hasItem($key);
        $f = $this->createCacheItem;

        if (!$isHit) {
            return $f($key, null, false);
        }

        return $f($key, $this->client->get($this->addNamespaceToKey($key)), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        $this->validateKeys($keys);

        $existingKeys = array_filter($keys, function ($key) {
            return $this->hasItem($key);
        });

        return $this->generateItems($existingKeys, array_diff($keys, $existingKeys));
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        CacheItem::validateKey($key);

        $namespacedKey = $this->addNamespaceToKey($key);

        if (isset($this->deferredItems[$key])) {
            return true;
        }

        try {
            $this->client->get($namespacedKey);
        } catch (\Exception $e) {
            return false;
        }

        return $this->isLastResultHit();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferredItems = array();

        $namespace = $this->client->increment($this->namespaceKey);

        if (false !== $namespace) {
            $this->namespace = $namespace;

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        CacheItem::validateKey($key);

        if (isset($this->deferredItems[$key])) {
            unset($this->deferredItems[$key]);
        }

        return $this->client->delete($this->addNamespaceToKey($key)) === true
            || $this->client->getResultCode() === \Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $this->validateKeys($keys);

        foreach ($this->client->deleteMulti($this->addNamespaceToKeys($keys)) as $result) {
            if ($result !== true && $result !== \Memcached::RES_NOTFOUND) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        return $this->saveDeferred($item) && $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        if (isset($this->deferredItems[$item->getKey()])) {
            return true;
        }

        $f = $this->getCacheItemAsArray;
        $expiry = $f($item)['expiry'];

        if ($expiry !== 0 && $expiry < time()) {
            return false;
        }

        $f = $this->changeIsHit;

        $this->deferredItems[$item->getKey()] = $f($item, true);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $deferredItemsByExpiry = array();

        foreach ($this->deferredItems as $deferredItem) {
            $f = $this->getCacheItemAsArray;
            $itemAsArray = $f($deferredItem);

            $deferredItemsByExpiry[$itemAsArray['expiry']][$this->addNamespaceToKey($itemAsArray['key'])] = $itemAsArray['value'];
        }

        foreach ($deferredItemsByExpiry as $expiry => $deferredItems) {
            $success = $this->client->setMulti($deferredItems, $expiry);

            if (false === $success) {
                $this->deferredItems = array();

                return false;
            }
        }

        $this->deferredItems = array();

        return true;
    }

    /**
     * @return bool
     */
    private function isLastResultHit()
    {
        return $this->client->getResultCode() === \Memcached::RES_SUCCESS;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function addNamespaceToKey($key)
    {
        return $this->namespace.$key;
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    private function addNamespaceToKeys(array $keys)
    {
        if (!empty($this->namespace)) {
            return array_map(function ($key) {
                return $this->addNamespaceToKey($key);
            }, $keys);
        }

        return $keys;
    }

    /**
     * @param array $keys
     *
     * @throws InvalidArgumentException
     */
    private function validateKeys(array $keys)
    {
        foreach ($keys as $key) {
            CacheItem::validateKey($key);
        }
    }

    /**
     * @param array $keys
     * @param array $notFoundKeys
     *
     * @return \Generator
     */
    private function generateItems(array $keys, array $notFoundKeys)
    {
        $values = $this->client->getMulti($this->addNamespaceToKeys($keys));

        $f = $this->createCacheItem;

        foreach ($keys as $key) {
            $namespacedKey = $this->addNamespaceToKey($key);
            $valueFound = isset($values[$namespacedKey]);

            yield $key => $f($key, $valueFound ? $values[$namespacedKey] : null, $valueFound);
        }

        foreach ($notFoundKeys as $notFoundKey) {
            yield $notFoundKey => $f($notFoundKey, null, false);
        }
    }
}
