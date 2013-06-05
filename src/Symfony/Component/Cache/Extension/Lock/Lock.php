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

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Lock
{
    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var KeyLock[]
     */
    private $acquired = array();

    /**
     * @var KeyLock[]
     */
    private $locked = array();

    /**
     * @param int $timeout
     * @param int $sleep
     */
    public function __construct($timeout, $sleep)
    {
        $this->timeout = $timeout;
        $this->sleep = $sleep;
    }

    /**
     * @param string  $key
     * @param KeyLock $keyLock
     *
     * @return Lock
     */
    public function add($key, KeyLock $keyLock)
    {
        $this->locked[$key] = $keyLock;

        return $this;
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
        $start = microtime(true) * 10e6;

        while (microtime(true) * 10e6 - $start < $this->timeout * 10e3) {
            foreach ($this->locked as $key => $keyLock) {
                if ($keyLock->acquire($cache)) {
                    $this->acquired[$key] = $keyLock;
                    unset($this->locked[$key]);
                }
            }

            if (empty($this->free)) {
                return true;
            }

            usleep($this->sleep * 10e3);
        }

        return false;
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
        $success = true;

        foreach ($this->acquired as $keyLock) {
            if (!$keyLock->release($cache)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param Cache $cache
     *
     * @return boolean
     */
    public function test(Cache $cache)
    {
        $free = true;

        foreach ($this->locked as $key) {
            if (!$key->test($cache)) {
                $free = false;
            }
        }

        return $free;
    }

    /**
     * @return boolean
     */
    public function isAcquired()
    {
        return empty($this->wasted);
    }

    /**
     * @return string[]
     */
    public function getLockedKeys()
    {
        return array_keys($this->locked);
    }

    /**
     * @return string[]
     */
    public function getAcquiredKeys()
    {
        return array_keys($this->acquired);
    }
}
