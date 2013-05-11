<?php

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
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (!isset($this->values[$key])) {
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
            if (isset($this->values[$key])) {
                $result[$key] = $this->values[$key];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $this->values[$key] = $value;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->values = array_merge($this->values, $values);

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
}
