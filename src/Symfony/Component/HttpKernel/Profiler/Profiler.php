<?php

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Profiler.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Profiler implements \ArrayAccess
{
    protected $profilerStorage;
    protected $collectors;
    protected $response;
    protected $logger;

    public function __construct(ProfilerStorage $profilerStorage, LoggerInterface $logger = null)
    {
        $this->profilerStorage = $profilerStorage;
        $this->logger = $logger;
        $this->collectors = array();
    }

    /**
     * Clones the Profiler instance.
     */
    public function __clone()
    {
        $this->profilerStorage = clone $this->profilerStorage;
    }

    /**
     * Returns a new Profiler for the given Response.
     *
     * @param Response $response A Response instance
     *
     * @return Profiler A new Profiler instance
     */
    public function load(Response $response)
    {
        if (!$token = $response->headers->get('X-Debug-Token')) {
            return null;
        }

        return $this->getProfilerForToken($token);
    }

    /**
     * Returns a new Profiler for the given token.
     *
     * @param string $token A token
     *
     * @return Profiler A new Profiler instance
     */
    public function getProfilerForToken($token)
    {
        $profiler = clone $this;
        $profiler->profilerStorage->setToken($token);
        $profiler->loadCollectorData();

        return $profiler;
    }

    /**
     * Collects data for the given Response.
     *
     * @param Response $response A Response instance
     */
    public function collect(Response $response)
    {
        $this->response = $response;
        $this->response->headers->set('X-Debug-Token', $this->profilerStorage->getToken());

        $data = array();
        foreach ($this->collectors as $name => $collector) {
            $collector->collect();

            $data[$name] = $collector->getData();
        }

        try {
            $this->profilerStorage->write($data);
            $this->profilerStorage->purge();
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->err('Unable to store the profiler information.');
            }
        }
    }

    /**
     * Loads the data stored in the storage for all collectors.
     */
    public function loadCollectorData()
    {
        try {
            foreach ($this->collectors as $name => $collector) {
                $collector->setData($this->profilerStorage->getData($name));
            }
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->err('Unable to read the profiler information.');
            }
        }
    }

    /**
     * Gets the profiler storage.
     *
     * @return ProfilerStorage A ProfilerStorage instance
     */
    public function getProfilerStorage()
    {
        return $this->profilerStorage;
    }

    /**
     * Gets the Response.
     *
     * @return Response A Response instance
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the Collectors associated with this profiler.
     *
     * @return array An array of collectors
     */
    public function getCollectors()
    {
        return $this->collectors;
    }

    /**
     * Sets the Collectors associated with this profiler.
     *
     * @param array $collectors An array of collectors
     */
    public function setCollectors(array $collectors = array())
    {
        $this->collectors = array();
        foreach ($collectors as $name => $collector) {
            $this->addCollector($collector);
        }
    }

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector A DataCollectorInterface instance
     */
    public function addCollector(DataCollectorInterface $collector)
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    /**
     * Returns true if a Collector for the given name exists.
     *
     * @param string $name A collector name
     */
    public function hasCollector($name)
    {
        return isset($this->collectors[$name]);
    }

    /**
     * Gets a Collector by name.
     *
     * @param string $name A collector name
     *
     * @return DataCollectorInterface A DataCollectorInterface instance
     *
     * @throws \InvalidArgumentException if the collector does not exist
     */
    public function getCollector($name)
    {
        if (!isset($this->collectors[$name])) {
            throw new \InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }

    /**
     * Returns true if the named collector exists.
     *
     * @param string $name The collector name
     *
     * @return Boolean true if the collector exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->hasCollector($name);
    }

    /**
     * Gets a collector.
     *
     * @param string $name The collector name
     *
     * @throws \InvalidArgumentException if the collector does not exist
     */
    public function offsetGet($name)
    {
        return $this->getCollector($name);
    }

    /**
     * Unimplemented.
     *
     * @param string       $name  The collector name
     * @param string|array $value The collector
     *
     * @throws \LogicException
     */
    public function offsetSet($name, $value)
    {
        throw new \LogicException('A Collector cannot be set.');
    }

    /**
     * Unimplemented.
     *
     * @param string $name The collector name
     *
     * @throws \LogicException
     */
    public function offsetUnset($name)
    {
        throw new \LogicException('A Collector cannot be removed.');
    }
}
