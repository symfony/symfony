<?php

namespace Symfony\Framework\ProfilerBundle\DataCollector;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\RequestHandler\Response;
use Symfony\Framework\ProfilerBundle\ProfilerStorage;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DataCollectorManager
{
  protected $container;
  protected $profilerStorage;
  protected $collectors;
  protected $response;
  protected $lifetime;

  public function __construct(ContainerInterface $container, ProfilerStorage $profilerStorage, $lifetime = 86400)
  {
    $this->container = $container;
    $this->lifetime = $lifetime;
    $this->profilerStorage = $profilerStorage;
    $this->collectors = $this->initCollectors();
  }

  public function register()
  {
    $this->container->getEventDispatcherService()->connect('core.response', array($this, 'handle'));
  }

  public function handle(Event $event, Response $response)
  {
    if (!$event->getParameter('main_request'))
    {
      return $response;
    }

    $this->response = $response;

    $data = array();
    foreach ($this->collectors as $name => $collector)
    {
      $data[$name] = $collector->getData();
    }
    $this->profilerStorage->write($data);
    $this->profilerStorage->purge($this->lifetime);

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
    $coreColectors = array();
    $userCollectors = array();
    foreach ($config as $id => $attributes)
    {
      $collector = $this->container->getService($id);
      $collector->setCollectorManager($this);

      if (isset($attributes[0]['core']) && $attributes[0]['core'])
      {
        $coreColectors[$collector->getName()] = $collector;
      }
      else
      {
        $userCollectors[$collector->getName()] = $collector;
      }
    }

    return $this->collectors = array_merge($coreColectors, $userCollectors);
  }
}
