<?php

namespace Symfony\Component\Cache\Lock;

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
    private $free = array();

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
        $this->free[$key] = $keyLock;

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
            foreach ($this->free as $key => $keyLock) {
                if ($keyLock->acquire($cache)) {
                    $this->acquired[$key] = $keyLock;
                    unset($this->free[$key]);
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
     * @return boolean
     */
    public function isAcquired()
    {
        return empty($this->wasted);
    }

    /**
     * @return string[]
     */
    public function getFreeKeys()
    {
        return array_keys($this->free);
    }

    /**
     * @return string[]
     */
    public function getAcquiredKeys()
    {
        return array_keys($this->acquired);
    }
}
