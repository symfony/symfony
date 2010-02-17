<?php

namespace Symfony\Framework\WebBundle\Listener;

use Symfony\Foundation\LoggerInterface;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\Routing\RouterInterface;

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
class RequestParser
{
  protected $container;
  protected $router;
  protected $logger;

  public function __construct(ContainerInterface $container, RouterInterface $router, LoggerInterface $logger = null)
  {
    $this->container = $container;
    $this->router = $router;
    $this->logger = $logger;
  }

  public function register()
  {
    $this->container->getEventDispatcherService()->connect('core.request', array($this, 'resolve'));
  }

  public function resolve(Event $event)
  {
    if (!$event->getParameter('main_request'))
    {
      return;
    }

    $request = $event->getParameter('request');

    $this->container->setParameter('request.base_path', $request->getBasePath());

    if ($request->getPathParameter('_bundle'))
    {
      return;
    }

    $this->router->setContext(array(
      'base_url'  => $request->getBaseUrl(),
      'method'    => $request->getMethod(),
      'host'      => $request->getHost(),
      'is_secure' => $request->isSecure(),
    ));

    if (false !== $parameters = $this->router->match($request->getPathInfo()))
    {
      if (null !== $this->logger)
      {
        $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], str_replace("\n", '', var_export($parameters, true))));
      }

      $request->setPathParameters($parameters);
    }
    elseif (null !== $this->logger)
    {
      $this->logger->err(sprintf('No route found for %s', $request->getPathInfo()));
    }
  }
}
