<?php

namespace Symfony\Foundation;

use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This EventDispatcher implementation uses a DependencyInjection contrainer to
 * lazy load listeners.
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
  protected $container;

  /**
   * Constructor.
   *
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;

    foreach ($container->findAnnotatedServiceIds('kernel.listener') as $id => $attributes)
    {
      foreach ($attributes as $attribute)
      {
        if (isset($attribute['event']))
        {
          $this->connect($attribute['event'], array($id, isset($attribute['method']) ? $attribute['method'] : 'handle'));
        }
      }
    }
  }

  /**
   * Returns all listeners associated with a given event name.
   *
   * @param  string   $name    The event name
   *
   * @return array  An array of listeners
   */
  public function getListeners($name)
  {
    if (!isset($this->listeners[$name]))
    {
      return array();
    }

    foreach ($this->listeners[$name] as $i => $listener)
    {
      if (is_array($listener) && is_string($listener[0]))
      {
        $this->listeners[$name][$i] = array($this->container->getService($listener[0]), $listener[1]);
      }
    }

    return $this->listeners[$name];
  }
}
