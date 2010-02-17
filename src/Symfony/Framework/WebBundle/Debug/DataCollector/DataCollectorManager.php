<?php

namespace Symfony\Framework\WebBundle\Debug\DataCollector;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\RequestHandler\Response;

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
  protected $token;
  protected $data;
  protected $collectors;
  protected $response;

  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    $this->token = uniqid();
    $this->collectors = $this->initCollectors();
  }

  public function register()
  {
    $this->container->getEventDispatcherService()->connect('core.response', array($this, 'handle'));
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
    $config = $this->container->findAnnotatedServiceIds('debug.collector');
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

  public function getData($name = null)
  {
    if (null === $name)
    {
      return $this->data;
    }

    return isset($this->data[$name]) ? $this->data[$name] : null;
  }

  public function handle(Event $event, Response $response)
  {
    if (!$event->getParameter('main_request'))
    {
      return $response;
    }

    $this->response = $response;

    foreach ($this->collectors as $name => $collector)
    {
      $this->data[$name] = $collector->collect();
    }

    return $response;
  }

  public function getToken()
  {
    return $this->token;
  }
}
