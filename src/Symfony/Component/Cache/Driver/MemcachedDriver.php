<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Driver;

/**
 * This is our Memcached cache driver implementation
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
class MemcachedDriver implements BatchDriverInterface
{

    /**
     * Memcached instance
     *
     * @var \Memcached
     */
    private $memcached;

    /**
     * Create our Memcached driver from existing Memcached object
     *
     * @param \Memcached $memcached
     */
    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSerializationSupport()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$exists = 0)
    {
        $value = $this->memcached->get($key);

        $exists = \Memcached::RES_NOTFOUND !== $this->memcached->getResultCode();

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        $result = null;

        $this->get($key, $result);

        return  $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data, $lifeTime = null)
    {
        if (false === $this->memcached->replace($key, $data, $lifeTime)) {
            return $this->memcached->set($key, $data, $lifeTime);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        return $this->memcached->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $items, $ttl = 0)
    {
        $results = $this->memcached->setMulti($items, $ttl);

        if (!is_array($results)) {
            $results = $this->convertToMultiResponse(array_keys($items), $results);
        } else {
            foreach ($results as $key => $value) {
                if ($value === \Memcached::RES_NOTFOUND) {
                    $results[$key] = false;
                } else {
                    $results[$key] = true;
                }
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys)
    {
        $results = $this->memcached->getMulti($keys);

        return $this->convertToMultiResponse($keys, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function removeMultiple(array $keys)
    {
        $results = array();

        foreach ($keys as $key) {
            $results[$key] = $this->remove($key);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function existsMultiple(array $keys)
    {
        $result = array();

        foreach ($keys as $key) {
            $result[$key] = $this->exists($key);
        }

        return $result;
    }


    /**
     * Convert a reponse from Memcached to a multi-reponse so that we can implement the interface properly
     *
     * @param array         $items
     * @param bool|array    $state
     *
     * @return bool[]
     */
    private function convertToMultiResponse($items, $state)
    {
        $results = array();

        if (is_array($state)) {
            foreach ($items as $key) {
                $results[$key] = array_key_exists($key, $state) ? $state[$key] : false;
            }
        } elseif ($state === true || $state === false) {
            foreach ($items as $key) {
                $results[$key] = $state;
            }
        }

        return $results;
    }
}
