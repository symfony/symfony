<?php

namespace Symfony\Components\DependencyInjection;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Container is a dependency injection container.
 *
 * It gives access to object instances (services), and parameters.
 *
 * Services and parameters are simple key/pair stores.
 *
 * Parameters keys are case insensitive.
 *
 * A service id can contain lowercased letters, digits, underscores, and dots.
 * Underscores are used to separate words, and dots to group services
 * under namespaces:
 *
 * <ul>
 *   <li>request</li>
 *   <li>mysql_session_storage</li>
 *   <li>symfony.mysql_session_storage</li>
 * </ul>
 *
 * A service can also be defined by creating a method named
 * getXXXService(), where XXX is the camelized version of the id:
 *
 * <ul>
 *   <li>request -> getRequestService()</li>
 *   <li>mysql_session_storage -> getMysqlSessionStorageService()</li>
 *   <li>symfony.mysql_session_storage -> getSymfony_MysqlSessionStorageService()</li>
 * </ul>
 *
 * The container can have three possible behaviors when a service does not exist:
 *
 *  * EXCEPTION_ON_INVALID_REFERENCE: Throws an exception (the default)
 *  * NULL_ON_INVALID_REFERENCE:      Returns null
 *  * IGNORE_ON_INVALID_REFERENCE:    Ignores the wrapping command asking for the reference
 *                                    (for instance, ignore a setter if the service does not exist)
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class Container implements ContainerInterface, \ArrayAccess, \Iterator
{
  protected
    $serviceIds = array(),
    $parameters = array(),
    $services   = array(),
    $count      = 0;

  const EXCEPTION_ON_INVALID_REFERENCE = 1;
  const NULL_ON_INVALID_REFERENCE      = 2;
  const IGNORE_ON_INVALID_REFERENCE    = 3;

  /**
   * Constructor.
   *
   * @param array $parameters An array of parameters
   */
  public function __construct(array $parameters = array())
  {
    $this->setParameters($parameters);
    $this->setService('service_container', $this);
  }

  /**
   * Sets the service container parameters.
   *
   * @param array $parameters An array of parameters
   */
  public function setParameters(array $parameters)
  {
    $this->parameters = array();
    foreach ($parameters as $key => $value)
    {
      $this->parameters[strtolower($key)] = $value;
    }
  }

  /**
   * Adds parameters to the service container parameters.
   *
   * @param array $parameters An array of parameters
   */
  public function addParameters(array $parameters)
  {
    $this->setParameters(array_merge($this->parameters, $parameters));
  }

  /**
   * Gets the service container parameters.
   *
   * @return array An array of parameters
   */
  public function getParameters()
  {
    return $this->parameters;
  }

  /**
   * Gets a service container parameter.
   *
   * @param  string $name The parameter name
   *
   * @return mixed  The parameter value
   *
   * @throws  \InvalidArgumentException if the parameter is not defined
   */
  public function getParameter($name)
  {
    if (!$this->hasParameter($name))
    {
      throw new \InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
    }

    return $this->parameters[strtolower($name)];
  }

  /**
   * Sets a service container parameter.
   *
   * @param string $name       The parameter name
   * @param mixed  $parameters The parameter value
   */
  public function setParameter($name, $value)
  {
    $this->parameters[strtolower($name)] = $value;
  }

  /**
   * Returns true if a parameter name is defined.
   *
   * @param  string  $name       The parameter name
   *
   * @return Boolean true if the parameter name is defined, false otherwise
   */
  public function hasParameter($name)
  {
    return array_key_exists(strtolower($name), $this->parameters);
  }

  /**
   * Sets a service.
   *
   * @param string $id      The service identifier
   * @param object $service The service instance
   */
  public function setService($id, $service)
  {
    $this->services[$id] = $service;
  }

  /**
   * Returns true if the given service is defined.
   *
   * @param  string  $id      The service identifier
   *
   * @return Boolean true if the service is defined, false otherwise
   */
  public function hasService($id)
  {
    return isset($this->services[$id]) || method_exists($this, 'get'.self::camelize($id).'Service');
  }

  /**
   * Gets a service.
   *
   * If a service is both defined through a setService() method and
   * with a set*Service() method, the former has always precedence.
   *
   * @param  string $id              The service identifier
   * @param  int    $invalidBehavior The behavior when the service does not exist
   *
   * @return object The associated service
   *
   * @throws \InvalidArgumentException if the service is not defined
   *
   * @see Reference
   */
  public function getService($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
  {
    if (isset($this->services[$id]))
    {
      return $this->services[$id];
    }

    if (method_exists($this, $method = 'get'.self::camelize($id).'Service'))
    {
      return $this->$method();
    }

    if (self::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior)
    {
      throw new \InvalidArgumentException(sprintf('The service "%s" does not exist.', $id));
    }
    else
    {
      return null;
    }
  }

  /**
   * Gets all service ids.
   *
   * @return array An array of all defined service ids
   */
  public function getServiceIds()
  {
    $ids = array();
    $r = new \ReflectionClass($this);
    foreach ($r->getMethods() as $method)
    {
      if (preg_match('/^get(.+)Service$/', $name = $method->getName(), $match))
      {
        $ids[] = self::underscore($match[1]);
      }
    }

    return array_merge($ids, array_keys($this->services));
  }

  /**
   * Returns true if the parameter name is defined (implements the ArrayAccess interface).
   *
   * @param  string  The parameter name
   *
   * @return Boolean true if the parameter name is defined, false otherwise
   */
  public function offsetExists($name)
  {
    return $this->hasParameter($name);
  }

  /**
   * Gets a service container parameter (implements the ArrayAccess interface).
   *
   * @param  string The parameter name
   *
   * @return mixed  The parameter value
   */
  public function offsetGet($name)
  {
    return $this->getParameter($name);
  }

  /**
   * Sets a parameter (implements the ArrayAccess interface).
   *
   * @param string The parameter name
   * @param mixed  The parameter value
   */
  public function offsetSet($name, $value)
  {
    $this->setParameter($name, $value);
  }

  /**
   * Removes a parameter (implements the ArrayAccess interface).
   *
   * @param string The parameter name
   */
  public function offsetUnset($name)
  {
    unset($this->parameters[$name]);
  }

  /**
   * Returns true if the container has a service with the given identifier.
   *
   * @param  string  The service identifier
   *
   * @return Boolean true if the container has a service with the given identifier, false otherwise
   */
  public function __isset($id)
  {
    return $this->hasService($id);
  }

  /**
   * Gets the service associated with the given identifier.
   *
   * @param  string The service identifier
   *
   * @return mixed  The service instance associated with the given identifier
   */
  public function __get($id)
  {
    return $this->getService($id);
  }

  /**
   * Sets a service.
   *
   * @param string The service identifier
   * @param mixed  A service instance
   */
  public function __set($id, $service)
  {
    $this->setService($id, $service);
  }

  /**
   * Removes a service by identifier.
   *
   * @param string The service identifier
   */
  public function __unset($id)
  {
    throw new \LogicException('You can\'t unset a service.');
  }

  /**
   * Resets the service identifiers array to the beginning (implements the Iterator interface).
   */
  public function rewind()
  {
    $this->serviceIds = $this->getServiceIds();

    $this->count = count($this->serviceIds);
  }

  /**
   * Gets the key associated with the current service (implements the Iterator interface).
   *
   * @return string The service identifier
   */
  public function key()
  {
    return current($this->serviceIds);
  }

  /**
   * Returns the current service (implements the Iterator interface).
   *
   * @return mixed The service
   */
  public function current()
  {
    return $this->getService(current($this->serviceIds));
  }

  /**
   * Moves to the next service (implements the Iterator interface).
   */
  public function next()
  {
    next($this->serviceIds);

    --$this->count;
  }

  /**
   * Returns true if the current service is valid (implements the Iterator interface).
   *
   * @return boolean The validity of the current service; true if it is valid
   */
  public function valid()
  {
    return $this->count > 0;
  }

  static public function camelize($id)
  {
    return preg_replace(array('/(^|_|-)+(.)/e', '/\.(.)/e'), array("strtoupper('\\2')", "'_'.strtoupper('\\1')"), $id);
  }

  static public function underscore($id)
  {
    return strtolower(preg_replace(array('/_/', '/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('.', '\\1_\\2', '\\1_\\2'), $id));
  }
}
