<?php

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;

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
     * {@inheritdoc}
     */
    public function fetch(DataInterface $data)
    {
        $missed = array();
        $fetched = null;

        foreach ($this->all() as $driver) {
            $fetched = $driver->fetch($data);

            if (null !== $fetched) {
                break;
            }

            $missed[] = $driver;
        }

        if (null === $fetched) {
            return new NullResult();
        }

        /** @var DriverInterface $driver */
        foreach ($missed as $driver) {
            $driver->store($fetched);
        }

        return $fetched;
    }

    /**
     * {@inheritdoc}
     */
    public function store(DataInterface $data)
    {
        $success = false;
        foreach ($this->all() as $driver) {
            if ($driver->store($data)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(KeyCollection $data)
    {
        $keys = new KeyCollection();
        foreach ($this->all() as $driver) {
            $keys->merge($driver->delete($data));
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $done = true;
        foreach ($this->all() as $driver) {
            if (!$driver->flush()) {
                $done = false;
            }
        }

        return $done;
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
