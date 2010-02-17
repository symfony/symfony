<?php

namespace Symfony\Framework\WebBundle\Listener;

use Symfony\Foundation\LoggerInterface;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\EventDispatcher\Event;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ControllerLoader listen to the core.load_controller and finds the controller
 * to execute based on the request parameters.
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerLoader
{
  protected $container;
  protected $logger;

  public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
  {
    $this->container = $container;
    $this->logger = $logger;
  }

  public function register()
  {
    $this->container->getEventDispatcherService()->connect('core.load_controller', array($this, 'resolve'));
  }

  public function run($controller, array $parameters)
  {
    $request = $this->container->getRequestService();

    list($parameters['_bundle'], $parameters['_controller'], $parameters['_action']) = explode(':', $controller);
    $parameters['_format'] = $request->getRequestFormat();

    $request = $request->duplicate(array('path' => $parameters));

    return $this->container->getRequestHandlerService()->handleRaw($request, false);
  }

  public function resolve(Event $event)
  {
    $request = $event->getParameter('request');

    if (!($bundle = $request->getPathParameter('_bundle')) || !($controller = $request->getPathParameter('_controller')) || !($action = $request->getPathParameter('_action')))
    {
      if (null !== $this->logger)
      {
        $this->logger->err(sprintf('Unable to look for the controller as some mandatory parameters are missing (_bundle: %s, _controller: %s, _action: %s)', isset($bundle) ? var_export($bundle, true) : 'NULL', isset($controller) ? var_export($controller, true) : 'NULL', isset($action) ? var_export($action, true) : 'NULL'));
      }

      return false;
    }

    $controller = $this->findController($bundle, $controller, $action);

    $r = new \ReflectionObject($controller[0]);
    $arguments = $this->getMethodArguments($r->getMethod($controller[1]), $event->getParameter('request')->getPathParameters(), sprintf('%s::%s()', get_class($controller[0]), $controller[1]));

    $event->setReturnValue(array($controller, $arguments));

    return true;
  }

  public function findController($bundle, $controller, $action)
  {
    $class = null;
    $logs = array();
    foreach (array_keys($this->container->getParameter('kernel.bundle_dirs')) as $namespace)
    {
      $try = $namespace.'\\'.$bundle.'\\Controller\\'.$controller.'Controller';
      if (!class_exists($try))
      {
        if (null !== $this->logger)
        {
          $logs[] = sprintf('Failed finding controller "%s:%s" from namespace "%s"', $bundle, $controller, $namespace);
        }
      }
      else
      {
        if (!in_array($namespace.'\\'.$bundle.'\\Bundle', array_map(function ($bundle) { return get_class($bundle); }, $this->container->getKernelService()->getBundles())))
        {
          throw new \LogicException(sprintf('To use the "%s" controller, you first need to enable the Bundle "%s" in your Kernel class.', $try, $namespace.'\\'.$bundle));
        }

        $class = $try;

        break;
      }
    }

    if (null === $class)
    {
      if (null !== $this->logger)
      {
        foreach ($logs as $log)
        {
          $this->logger->info($log);
        }
      }

      throw new \InvalidArgumentException(sprintf('Unable to find controller "%s:%s".', $bundle, $controller));
    }

    $controller = new $class($this->container);

    $method = $action.'Action';
    if (!method_exists($controller, $method))
    {
      throw new \InvalidArgumentException(sprintf('Method "%s::%s" does not exist.', $class, $method));
    }

    if (null !== $this->logger)
    {
      $this->logger->info(sprintf('Using controller "%s::%s"%s', $class, $method, isset($file) ? sprintf(' from file "%s"', $file) : ''));
    }

    return array($controller, $method);
  }

  public function getMethodArguments(\ReflectionFunctionAbstract $r, array $parameters, $controller)
  {
    $arguments = array();
    foreach ($r->getParameters() as $param)
    {
      if (array_key_exists($param->getName(), $parameters))
      {
        $arguments[] = $parameters[$param->getName()];
      }
      elseif ($param->isDefaultValueAvailable())
      {
        $arguments[] = $param->getDefaultValue();
      }
      else
      {
        throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $controller, $param->getName()));
      }
    }

    return $arguments;
  }
}
