<?php

namespace Symfony\Components\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\Loader;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A BuilderConfiguration is a consistent set of definitions and parameters.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuilderConfiguration
{
  protected $definitions = array();
  protected $parameters  = array();
  protected $aliases     = array();
  protected $resources   = array();

  public function __construct(array $definitions = array(), array $parameters = array())
  {
    $this->setDefinitions($definitions);
    $this->setParameters($parameters);
  }

  /**
   * Returns an array of resources loaded to build this configuration.
   *
   * @return array An array of resources
   */
  public function getResources()
  {
    return $this->resources;
  }

  /**
   * Adds a resource for this configuration.
   *
   * @param ResourceInterface $resource A resource instance
   *
   * @return BuilderConfiguration The current instance
   */
  public function addResource(ResourceInterface $resource)
  {
    $this->resources[] = $resource;

    return $this;
  }

  /**
   * Merges a BuilderConfiguration with the current one.
   *
   * @param BuilderConfiguration $configuration
   *
   * @return BuilderConfiguration The current instance
   */
  public function merge(BuilderConfiguration $configuration = null)
  {
    if (null === $configuration)
    {
      return;
    }

    $this->addDefinitions($configuration->getDefinitions());
    $this->addAliases($configuration->getAliases());
    $this->addParameters($configuration->getParameters());

    foreach ($configuration->getResources() as $resource)
    {
      $this->addResource($resource);
    }

    return $this;
  }

  /**
   * Merges the configuration given by an extension.
   *
   * @param $key    string The extension tag to load (namespace.tag)
   * @param $values array  An array of values to customize the extension
   *
   * @return BuilderConfiguration The current instance
   */
  public function mergeExtension($key, array $values = array())
  {
    list($namespace, $tag) = explode('.', $key);

    $config = Loader::getExtension($namespace)->load($tag, $values);

    $this->merge($config);

    return $this;
  }

  /**
   * Sets the service container parameters.
   *
   * @param array $parameters An array of parameters
   *
   * @return BuilderConfiguration The current instance
   */
  public function setParameters(array $parameters)
  {
    $this->parameters = array();
    foreach ($parameters as $key => $value)
    {
      $this->parameters[strtolower($key)] = $value;
    }

    return $this;
  }

  /**
   * Adds parameters to the service container parameters.
   *
   * @param array $parameters An array of parameters
   *
   * @return BuilderConfiguration The current instance
   */
  public function addParameters(array $parameters)
  {
    $this->setParameters(array_merge($this->parameters, $parameters));

    return $this;
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
   *
   * @return BuilderConfiguration The current instance
   */
  public function setParameter($name, $value)
  {
    $this->parameters[strtolower($name)] = $value;

    return $this;
  }

  /**
   * Sets an alias for an existing service.
   *
   * @param string $alias The alias to create
   * @param string $id    The service to alias
   *
   * @return BuilderConfiguration The current instance
   */
  public function setAlias($alias, $id)
  {
    unset($this->definitions[$alias]);

    $this->aliases[$alias] = $id;

    return $this;
  }

  /**
   * Adds definition aliases.
   *
   * @param array $aliases An array of aliases
   *
   * @return BuilderConfiguration The current instance
   */
  public function addAliases(array $aliases)
  {
    foreach ($aliases as $alias => $id)
    {
      $this->setAlias($alias, $id);
    }

    return $this;
  }

  /**
   * Gets all defined aliases.
   *
   * @return array An array of aliases
   */
  public function getAliases()
  {
    return $this->aliases;
  }

  /**
   * Returns true if a service alias exists.
   *
   * @param  string  $alias The alias
   *
   * @return Boolean true if the alias exists, false otherwise
   */
  public function hasAlias($alias)
  {
    return array_key_exists($alias, $this->aliases);
  }

  /**
   * Gets the service id for a given alias.
   *
   * @param  string $alias The alias
   *
   * @return string The aliased id
   *
   * @throws \InvalidArgumentException if the service alias does not exist
   */
  public function getAlias($alias)
  {
    if (!$this->hasAlias($alias))
    {
      throw new \InvalidArgumentException(sprintf('The service alias "%s" does not exist.', $alias));
    }

    return $this->aliases[$alias];
  }

  /**
   * Sets a definition.
   *
   * @param  string     $id         The identifier
   * @param  Definition $definition A Definition instance
   *
   * @return BuilderConfiguration The current instance
   */
  public function setDefinition($id, Definition $definition)
  {
    unset($this->aliases[$id]);

    return $this->definitions[$id] = $definition;

    return $this;
  }

  /**
   * Adds the definitions.
   *
   * @param array $definitions An array of definitions
   *
   * @return BuilderConfiguration The current instance
   */
  public function addDefinitions(array $definitions)
  {
    foreach ($definitions as $id => $definition)
    {
      $this->setDefinition($id, $definition);
    }

    return $this;
  }

  /**
   * Sets the definitions.
   *
   * @param array $definitions An array of definitions
   *
   * @return BuilderConfiguration The current instance
   */
  public function setDefinitions(array $definitions)
  {
    $this->definitions = array();
    $this->addDefinitions($definitions);

    return $this;
  }

  /**
   * Gets all definitions.
   *
   * @return array An array of Definition instances
   */
  public function getDefinitions()
  {
    return $this->definitions;
  }

  /**
   * Returns true if a service definition exists under the given identifier.
   *
   * @param  string  $id The service identifier
   *
   * @return Boolean true if the service definition exists, false otherwise
   */
  public function hasDefinition($id)
  {
    return array_key_exists($id, $this->definitions);
  }

  /**
   * Gets a service definition.
   *
   * @param  string  $id The service identifier
   *
   * @return Definition A Definition instance
   *
   * @throws \InvalidArgumentException if the service definition does not exist
   */
  public function getDefinition($id)
  {
    if (!$this->hasDefinition($id))
    {
      throw new \InvalidArgumentException(sprintf('The service definition "%s" does not exist.', $id));
    }

    return $this->definitions[$id];
  }

  /**
   * Gets a service definition by id or alias.
   *
   * The method "unaliases" recursively to return a Definition instance.
   *
   * @param  string  $id The service identifier or alias
   *
   * @return Definition A Definition instance
   *
   * @throws \InvalidArgumentException if the service definition does not exist
   */
  public function findDefinition($id)
  {
    if ($this->hasAlias($id))
    {
      return $this->findDefinition($this->getAlias($id));
    }

    return $this->getDefinition($id);
  }
}
