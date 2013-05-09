<?php

namespace Symfony\Component\Cache;

use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Driver\DriverInterface;
use Symfony\Component\Cache\Extension\ExtensionInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Cache
{
    /**
     * @var DriverInterface
     */
    private $driver;

    /**
     * @var ExtensionInterface
     */
    private $extension;

    /**
     * @var Options
     */
    private $options;

    /**
     * @param DriverInterface    $driver
     * @param ExtensionInterface $extension
     * @param array              $options
     */
    public function __construct(DriverInterface $driver, ExtensionInterface $extension, array $options = array())
    {
        $this->driver = $driver;
        $this->extension = $extension->setCache($this);
        $this->options = new Options($extension, $options);
    }

    /**
     * @return DriverInterface
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return ExtensionInterface
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Fetches item matching given query from cache.
     *
     * @param string|array $query
     * @param array        $options
     *
     * @return DataInterface
     */
    public function fetch($query, array $options = array())
    {
        $options = $this->options->resolve($options);
        $query = $this->resolveQuery($query);
        $keys = $this->extension->resolveFetch($query, $options);

        if ($keys->isEmpty()) {
            return new NullResult();
        }

        $result = $this->driver->fetch($keys);

        return $this->extension->buildResult($result, $options);
    }

    /**
     * Stores an item into the cache.
     *
     * @param DataInterface $data
     * @param array         $options
     *
     * @return DataInterface
     */
    public function store(DataInterface $data, array $options = array())
    {
        $options = $this->options->resolve($options);
        $data = $this->extension->prepareStorage($data, $options);

        return $this->driver->store($data);
    }

    /**
     * Deletes item matching given query from cache.
     *
     * @param string|array $query
     * @param array        $options
     *
     * @return KeyCollection
     */
    public function delete($query, array $options = array())
    {
        $options = $this->options->resolve($options);
        $query = $this->resolveQuery($query);
        $keys = $this->extension->resolveDeletion($query, $options);

        if ($keys->isEmpty()) {
            return $keys;
        }

        $keys->merge($this->extension->propagateDeletion($keys, $options));

        return $this->driver->delete($keys);
    }

    /**
     * Flushes cache.
     *
     * @param array $options
     *
     * @return boolean
     */
    public function flush(array $options = array())
    {
        $options = $this->options->resolve($options);
        $this->extension->prepareFlush($options);

        return $this->driver->flush();
    }

    /**
     * @param string|array $query
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function resolveQuery($query)
    {
        if (is_string($query)) {
            return array('key' => array($query));
        }

        if (is_array($query)) {
            if (isset($query['key']) && is_string($query['key'])) {
                $query['key'] = array($query['key']);
            }

            return $query;
        }

        throw new \InvalidArgumentException('Query must be string or array.');
    }
}
