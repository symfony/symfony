<?php

namespace Symfony\Framework\ProfilerBundle\DataCollector;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\HttpKernel\HttpKernelInterface;
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
 * DataCollectorManager.
 *
 * @package    Symfony
 * @subpackage Framework_ProfilerBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DataCollectorManager
{
    protected $container;
    protected $profilerStorage;
    protected $collectors;
    protected $response;
    protected $lifetime;
    protected $logger;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, ProfilerStorage $profilerStorage, $lifetime = 86400)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->lifetime = $lifetime;
        $this->profilerStorage = $profilerStorage;
        $this->collectors = $this->initCollectors();
    }

    /**
     * Registers a core.response listener.
     *
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.response', array($this, 'handle'));
    }

    public function handle(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return $response;
        }

        $this->response = $response;

        $data = array();
        foreach ($this->collectors as $name => $collector) {
            $data[$name] = $collector->getData();
        }

        try {
            $this->profilerStorage->write($data);
            $this->profilerStorage->purge($this->lifetime);
        } catch (\Exception $e) {
            $this->logger->err('Unable to store the profiler information.');
        }

        return $response;
    }

    public function getProfilerStorage()
    {
        return $this->profilerStorage;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getCollectors()
    {
        return $this->collectors;
    }

    public function initCollectors()
    {
        $config = $this->container->findAnnotatedServiceIds('data_collector');
        $ids = array();
        $coreCollectors = array();
        $userCollectors = array();
        foreach ($config as $id => $attributes) {
            $collector = $this->container->getService($id);
            $collector->setCollectorManager($this);

            if (isset($attributes[0]['core']) && $attributes[0]['core']) {
                $coreCollectors[$collector->getName()] = $collector;
            } else {
                $userCollectors[$collector->getName()] = $collector;
            }
        }

        return $this->collectors = array_merge($coreCollectors, $userCollectors);
    }
}
