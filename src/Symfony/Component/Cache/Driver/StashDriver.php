<?php

namespace Symfony\Component\Cache\Driver;

use Stash\Driver\DriverInterface as StashDriverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class StashDriver implements DriverInterface
{
    /**
     * @var StashDriverInterface
     */
    private $driver;

    /**
     * @param StashDriverInterface $driver
     */
    public function __construct(StashDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $data = $this->driver->getData(array($key));

        return empty($data) ? false : reset($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys)
    {
        $result = array();
        foreach ($keys as $key) {
            $data = $this->driver->getData(array($key));
            if (!empty($data)) {
                $result[$key] = reset($data);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->driver->storeData(array($key), $value, $ttl ?: 0);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->driver->storeData(array($key), $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        return $this->driver->clear(array($key));
    }

    /**
     * {@inheritdoc}
     */
    public function removeMultiple($keys)
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->driver->clear(array($key))) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->driver->clear();
    }
}
