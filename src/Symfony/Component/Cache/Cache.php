<?php

namespace Symfony\Component\Cache;

use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Data\ValidItem;
use Symfony\Component\Cache\Driver\DriverInterface;
use Symfony\Component\Cache\Exception\InvalidQueryException;
use Symfony\Component\Cache\Extension\ExtensionInterface;
use Symfony\Component\Cache\Exception\ExtensionDependencyException;

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
     *
     * @throws ExtensionDependencyException
     */
    public function __construct(DriverInterface $driver, ExtensionInterface $extension, array $options = array())
    {
        if (0 < count($extension->getRequiredExtensions())) {
            throw new ExtensionDependencyException('Provided extension requires other extensions, you should use an extension stack to include dependencies.');
        }

        $this->driver = $driver;
        $this->extension = $extension->setCache($this);
        $this->options = new Options($extension, $options);
    }

    /**
     * Fetches item matching given query from cache.
     *
     * @param string|array $query
     * @param array        $options
     *
     * @throws InvalidQueryException
     *
     * @return DataInterface
     */
    public function get($query, array $options = array())
    {
        $options = $this->options->resolve($options);
        $query = $this->resolveQuery($query);

        if (!$this->extension->supportsQuery($query, $options)) {
            throw InvalidQueryException::unsupported('Registered extension does not support "%s" query.', $query);
        }

        $keyCollection = $this->extension->resolveQuery($query, $options);

        if ($keyCollection->isEmpty()) {
            return new NullResult();
        }

        $keys = $keyCollection->getKeys();
        if (1 === count($keys)) {
            $result = $this->driver->get($key = reset($keys));
            if (false === $result) {
                return new NullResult();
            }
            $result = new CachedItem($key, $result);
        } else {
            $result = $this->driver->getMultiple($keys);
            if (empty($result)) {
                return new NullResult();
            }
            $result = Collection::fromCachedValues($result);
        }

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
    public function set(DataInterface $data, array $options = array())
    {
        $options = $this->options->resolve($options);
        $data = $this->extension->prepareStorage($data, $options);

        if ($data instanceof ValidItem && $this->driver->set($data->getKey(), $data->getValue())) {
            return new CachedItem($data->getKey(), $data->getValue());
        }

        if ($data instanceof Collection && $this->driver->setMultiple($data->getValues())) {
            return Collection::fromCachedValues($data->getValues());
        }

        return new NullResult();
    }

    /**
     * Removes item matching given query from cache.
     *
     * @param string|array $query
     * @param array        $options
     *
     * @throws InvalidQueryException
     *
     * @return KeyCollection
     */
    public function remove($query, array $options = array())
    {
        $options = $this->options->resolve($options);
        $query = $this->resolveQuery($query);

        if (!$this->extension->supportsQuery($query, $options)) {
            throw InvalidQueryException::unsupported('Registered extension does not support "%s" query.', $query);
        }

        $keyCollection = $this->extension->resolveRemoval($query, $options);

        if ($keyCollection->isEmpty()) {
            return $keyCollection;
        }

        $keyCollection->merge($this->extension->propagateRemoval($keyCollection, $options));

        $keys = $keyCollection->getKeys();
        if ((1 === count($keys) && $this->driver->remove(reset($keys))) || $this->driver->removeMultiple($keys)) {
            return new KeyCollection($keys);
        }

        return new KeyCollection();
    }

    /**
     * Flushes cache.
     *
     * @param array $options
     *
     * @return boolean
     */
    public function clear(array $options = array())
    {
        $options = $this->options->resolve($options);
        $this->extension->prepareClear($options);

        return $this->driver->clear();
    }

    /**
     * @param string|array $query
     *
     * @return array
     *
     * @throws InvalidQueryException
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

        throw InvalidQueryException::wrongType('Query must be string or array, "%s" given.', $query);
    }
}
