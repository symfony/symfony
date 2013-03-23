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
 * This is our Memory / array / runtime cache driver implementation
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
class MemoryDriver implements DriverInterface
{
    /**
     * @var array
     */
    private $cache = array();

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
        if (array_key_exists($key, $this->cache)) {
            $exists = true;

            return $this->cache[$key];
        }

        $exists = false;
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $data, $lifeTime = 0)
    {
        $this->cache[$key] = $data;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        if (array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

}
