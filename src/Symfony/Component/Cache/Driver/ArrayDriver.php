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
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ArrayDriver implements DriverInterface
{
    /**
     * @var array
     */
    private $values = array();

    /**
     * @var int[]
     */
    private $expiration = array();

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (!isset($this->values[$key])) {
            return false;
        }

        if (0 < $this->expiration[$key] && time() < $this->expiration[$key]) {
            $this->remove($key);

            return false;
        }

        return $this->values[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys)
    {
        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $this->values[$key] = $value;
        $this->expiration[$key] = $ttl ? time() + $ttl : 0;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        foreach ($this->values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        if (isset($this->values[$key])) {
            unset($this->values[$key]);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function removeMultiple($keys)
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->values = array();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'array';
    }
}
