<?php

namespace Symfony\Components\DependencyInjection;

use Symfony\Components\DependencyInjection\Loader\LoaderExtensionInterface;
use Symfony\Components\DependencyInjection\Resource\ResourceInterface;
use Symfony\Components\DependencyInjection\Resource\FileResource;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBag;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A BuilderConfiguration is a consistent set of definitions and parameters.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuilderConfiguration
{
    protected $definitions;
    protected $parameterBag;
    protected $aliases;
    protected $resources;
    protected $extensions;

    public function __construct(array $definitions = array(), ParameterBagInterface $parameterBag = null)
    {
        $this->aliases    = array();
        $this->resources  = array();
        $this->extensions = array();

        $this->setDefinitions($definitions);
        $this->parameterBag = null === $parameterBag ? new ParameterBag() : $parameterBag;
    }

    /**
     * Returns an array of resources loaded to build this configuration.
     *
     * @return ResourceInterface[] An array of resources
     */
    public function getResources()
    {
        return array_unique($this->resources);
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
        if (null === $configuration) {
            return;
        }

        $this->addDefinitions($configuration->getDefinitions());
        $this->addAliases($configuration->getAliases());
        $this->parameterBag->add($configuration->getParameterBag()->all());

        foreach ($configuration->getResources() as $resource) {
            $this->addResource($resource);
        }

        return $this;
    }

    /**
     * Loads the configuration for an extension.
     *
     * @param $extension LoaderExtensionInterface A LoaderExtensionInterface instance
     * @param $tag       string                   The extension tag to load (without the namespace - namespace.tag)
     * @param $values    array                    An array of values that customizes the extension
     *
     * @return BuilderConfiguration The current instance
     */
    public function loadFromExtension(LoaderExtensionInterface $extension, $tag, array $values = array())
    {
        $namespace = $extension->getAlias();

        $this->addObjectResource($extension);

        if (!isset($this->extensions[$namespace])) {
            $this->extensions[$namespace] = new self();

            $r = new \ReflectionObject($extension);
            $this->extensions[$namespace]->addResource(new FileResource($r->getFileName()));
        }

        $this->extensions[$namespace] = $extension->load($tag, $values, $this->extensions[$namespace]);

        return $this;
    }

    /**
     * Adds the object class hierarchy as resources.
     *
     * @param object $object An object instance
     */
    public function addObjectResource($object)
    {
        $parent = new \ReflectionObject($object);
        $this->addResource(new FileResource($parent->getFileName()));
        while ($parent = $parent->getParentClass()) {
            $this->addResource(new FileResource($parent->getFileName()));
        }
    }

    /**
     * Merges the extension configuration.
     *
     * @return BuilderConfiguration The current instance
     */
    public function mergeExtensionsConfiguration()
    {
        foreach ($this->extensions as $name => $configuration) {
            $this->merge($configuration);
        }

        $this->extensions = array();

        return $this;
    }

    /**
     * Gets the parameter bag.
     *
     * @return Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface A ParameterBagInterface instance
     */
    public function getParameterBag()
    {
        return $this->parameterBag;
    }

    /**
     * Gets a parameter.
     *
     * @param  string $name The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws  \InvalidArgumentException if the parameter is not defined
     */
    public function getParameter($name)
    {
        return $this->parameterBag->get($name);
    }

    /**
     * Sets a parameter.
     *
     * @param string $name       The parameter name
     * @param mixed  $parameters The parameter value
     */
    public function setParameter($name, $value)
    {
        $this->parameterBag->set($name, $value);
    }

    /**
     * Checks if a parameter is defined.
     *
     * @param string $name The parameter name
     */
    public function hasParameter($name)
    {
        return $this->parameterBag->has($name);
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
        foreach ($aliases as $alias => $id) {
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
        if (!$this->hasAlias($alias)) {
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

        $this->definitions[$id] = $definition;

        return $this;
    }

    /**
     * Adds the definitions.
     *
     * @param Definition[] $definitions An array of definitions
     *
     * @return BuilderConfiguration The current instance
     */
    public function addDefinitions(array $definitions)
    {
        foreach ($definitions as $id => $definition) {
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
        if (!$this->hasDefinition($id)) {
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
        if ($this->hasAlias($id)) {
            return $this->findDefinition($this->getAlias($id));
        }

        return $this->getDefinition($id);
    }
}
