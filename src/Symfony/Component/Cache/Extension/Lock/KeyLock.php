<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Extension\Lock;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\NullResult;

/**
 * Represents a key attached to a lock.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class KeyLock
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $id;

    /**
     * @var boolean
     */
    private $acquired = false;

    /**
     * @param string $key
     * @param string $id
     */
    public function __construct($key, $id)
    {
        $this->key = $key;
        $this->id = $id;
    }

    /**
     * Tries to acquire the lock.
     *
     * @param Cache $cache
     *
     * @return boolean
     */
    public function acquire(Cache $cache)
    {
        if ($this->acquired) {
            return true;
        }

        $result = $cache->get(array('key' => $this->key));

        if ($result instanceof CachedItem && $this->id !== $result->getValue()) {
            return false;
        }

        return $this->acquired = $cache->set(new FreshItem($this->key, $this->id));
    }

    /**
     * Releases the lock.
     *
     * @param Cache $cache
     *
     * @return boolean
     */
    public function release(Cache $cache)
    {
        $item = $cache->get(array('key' => $this->key));

        if ($item instanceof NullResult) {
            return true;
        }

        /** @var CachedItem $item */
        if ($this->id !== $item->getValue()) {
            return false;
        }

        return !$cache->remove($this->key)->isEmpty();
    }

    /**
     * @param Cache $cache
     *
     * @return boolean
     */
    public function test(Cache $cache)
    {
        $result = $cache->get(array('key' => $this->key));

        return !($result instanceof CachedItem && $this->id !== $result->getValue());
    }

    /**
     * @return boolean
     */
    public function isAcquired()
    {
        return $this->acquired;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }
}
