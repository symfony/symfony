<?php

namespace Symfony\Framework\ProfilerBundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\HttpKernel\Response;
use Symfony\Framework\ProfilerBundle\ProfilerStorage;
use Symfony\Foundation\LoggerInterface;

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
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Profiler implements \ArrayAccess
{
    protected $container;
    protected $profilerStorage;
    protected $collectors;
    protected $response;
    protected $logger;

    public function __construct(ContainerInterface $container, ProfilerStorage $profilerStorage, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->profilerStorage = $profilerStorage;
        $this->logger = $logger;
        $this->initCollectors();
        $this->loadCollectorData();
    }

    public function __clone()
    {
        $this->profilerStorage = clone $this->profilerStorage;
    }

    public function load(Response $response)
    {
        return $this->getProfilerForToken($response->headers->get('X-Debug-Token'));
    }

    public function getProfilerForToken($token)
    {
        $profiler = clone $this;
        $profiler->profilerStorage->setToken($token);
        $profiler->loadCollectorData();

        return $profiler;
    }

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

    public function getProfilerStorage()
    {
        return $this->profilerStorage;
    }

    public function getResponse()
    {
        return $this->response;
    }

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

    public function getCollectors()
    {
        return $this->collectors;
    }

    public function hasCollector($name)
    {
        return isset($this->collectors[$name]);
    }

    public function getCollector($name)
    {
        if (!isset($this->collectors[$name])) {
            throw new \InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     *
     * @param Boolean true if the field exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->hasCollector($name);
    }

    /**
     * Gets the value of a field.
     *
     * @param string $name The field name
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet($name)
    {
        return $this->getCollector($name);
    }

    /**
     * Sets the value of a field.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetSet($name, $value)
    {
        throw new \LogicException('The Collectors cannot be set.');
    }

    /**
     * Unimplemented.
     *
     * @param string $name The field name
     */
    public function offsetUnset($name)
    {
        throw new \LogicException('The Collectors cannot be removed.');
    }

    protected function initCollectors()
    {
        $config = $this->container->findAnnotatedServiceIds('data_collector');
        $ids = array();
        $coreCollectors = array();
        $userCollectors = array();
        foreach ($config as $id => $attributes) {
            $collector = $this->container->getService($id);
            $collector->setProfiler($this);

            if (isset($attributes[0]['core']) && $attributes[0]['core']) {
                $coreCollectors[$collector->getName()] = $collector;
            } else {
                $userCollectors[$collector->getName()] = $collector;
            }
        }

        $this->collectors = array_merge($coreCollectors, $userCollectors);
    }
}
