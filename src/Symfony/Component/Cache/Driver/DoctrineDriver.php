<?php

namespace Symfony\Component\Cache\Driver;

use Doctrine\Common\Cache\Cache as DoctrineDriverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DoctrineDriver extends AbstractDriver
{
    /**
     * @var DoctrineDriverInterface
     */
    private $driver;

    /**
     * @param DoctrineDriverInterface $driver
     */
    public function __construct(DoctrineDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchOne($key)
    {
        $fetchedData = $this->driver->fetch($key);

        return false === $fetchedData ? array() : array($key => $fetchedData);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchMany(array $keys)
    {
        $result = array();
        foreach ($keys as $key) {
            $fetchedData = $this->driver->fetch($key);

            if (false !== $fetchedData) {
                $result[$key] = $fetchedData;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeOne($key, $data)
    {
        if ($this->driver->save($key, $data)) {
            return array($key => $data);
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function storeMany(array $data)
    {
        $storedData = array();
        foreach ($data as $key => $value) {
            if ($this->driver->save($key, $value)) {
                $storedData[$key] = $value;
            }
        }

        return $storedData;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteOne($key)
    {
        return $this->driver->delete($key) ? array($key) : array();
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteMany(array $keys)
    {
        $deletedKeys = array();
        foreach ($keys as $key) {
            if ($this->driver->delete($key)) {
                $deletedKeys[] = $key;
            }
        }

        return $deletedKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        // TODO: Implement flush() method.
    }
}
