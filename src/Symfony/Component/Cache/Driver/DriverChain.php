<?php

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Exception\ObjectNotFoundException;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DriverChain implements DriverInterface
{
    /**
     * @var array
     */
    private $drivers;

    /**
     * @var boolean
     */
    private $sorted = true;

    /**
     * @param string          $name
     * @param DriverInterface $driver
     * @param int             $priority
     *
     * @return DriverChain
     */
    public function register($name, DriverInterface $driver, $priority = 0)
    {
        $this->sorted = false;
        $this->drivers[$name] = array(
            'index'    => count($this->drivers),
            'driver'   => $driver,
            'priority' => $priority,
        );

        return $this;
    }

    /**
     * @param string $name
     *
     * @return DriverInterface
     *
     * @throws ObjectNotFoundException
     */
    public function getDriver($name)
    {
        if (!isset($this->drivers[$name])) {
            throw new ObjectNotFoundException(sprintf(
                'Driver chain does not contain driver named "%s", presents ones are "%s".',
                $name, implode('", "', array_keys($this->drivers))
            ));
        }

        return $this->drivers[$name]['extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $missed = array();
        $value = null;

        foreach ($this->all() as $driver) {
            $value = $driver->get($key);

            if (null !== $value) {
                break;
            }

            $missed[] = $driver;
        }

        if (null === $value) {
            return false;
        }

        /** @var DriverInterface $driver */
        foreach ($missed as $driver) {
            $driver->set($key, $value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys)
    {
        $missed = array();
        $values = false;

        foreach ($this->all() as $driver) {
            $values = $driver->getMultiple($keys);

            if (false !== $values) {
                break;
            }

            $missed[] = $driver;
        }

        if (false === $values) {
            return false;
        }

        /** @var DriverInterface $driver */
        foreach ($missed as $driver) {
            $driver->setMultiple($values);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $success = true;
        foreach ($this->all() as $driver) {
            if (!$driver->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($this->all() as $driver) {
            if (!$driver->set($values, $ttl)) {
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
        $success = true;
        foreach ($this->all() as $driver) {
            if (!$driver->remove($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function removeMultiple($keys)
    {
        $success = true;
        foreach ($this->all() as $driver) {
            if (!$driver->removeMultiple($keys)) {
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
        $success = true;
        foreach ($this->all() as $driver) {
            if (!$driver->clear()) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @return DriverInterface[]
     */
    public function all()
    {
        $this->sort();

        $drivers = array();
        foreach ($this->drivers as $driver) {
            $drivers[] = $driver['driver'];
        }

        return $drivers;
    }

    private function sort()
    {
        if ($this->sorted) {
            return;
        }

        uasort($this->drivers, function (array $a, array $b) {
            return $a['priority'] === $b['priority']
                ? ($b['index'] - $a['index'])
                : $b['priority'] - $a['priority'];
        });
    }
}
