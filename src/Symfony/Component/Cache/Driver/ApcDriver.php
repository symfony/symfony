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
 * This is our APC cache driver implementation
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
class ApcDriver implements BatchDriverInterface
{
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
    public function get($key, &$exists = null)
    {
        return apc_fetch($key, $exists);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return apc_exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data, $lifeTime = 0)
    {
        return apc_store($key, $data, (int)$lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        return apc_delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $items, $ttl = 0)
    {
        $result = apc_store($items, null, $ttl);

        $keys = array_keys($items);

        $results = array();

        if (is_array($result)) {
            foreach ($keys as $key) {
                $results[$key] = !in_array($key, $result);
            }
        } elseif ($result === true || $result === false) {
            foreach ($items as $key) {
                $results[$key] = $result;
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys)
    {
        return apc_fetch($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function removeMultiple(array $keys)
    {
        $result = apc_delete($keys);

        $results = array();

        if (is_array($result)) {
            foreach ($keys as $key) {
                $results[$key] = !in_array($key, $result);
            }
        } elseif ($result === true || $result === false) {
            foreach ($keys as $key) {
                $results[$key] = $result;
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function existsMultiple(array $keys)
    {
        $result = $this->convertToMultiResponse($keys, false);

        return apc_exists($keys) + $result;
    }

    /**
     * Convert a reponse from APC to a multi-reponse so that we can implement the interface properly
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
