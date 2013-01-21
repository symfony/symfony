<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * ContainerBuilder is a DI container that provides an API to easily describe services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class ContainerBuilder extends Container implements TaggedContainerInterface
{
    private $extensions       = array();
    private $extensionsByNs   = array();
    private $definitions      = array();
    private $aliases          = array();
    private $resources        = array();
    private $extensionConfigs = array();
    private $compiler;

    /**
     * Registers an extension.
     *
     * @param ExtensionInterface $extension An extension instance
     *
     * @api
     */
    public function registerExtension(ExtensionInterface $extension)
    {
        $this->extensions[$extension->getAlias()] = $extension;

        if (false !== $extension->getNamespace()) {
            $this->extensionsByNs[$extension->getNamespace()] = $extension;
        }
    }

    /**
     * Returns an extension by alias or namespace.
     *
     * @param string $name An alias or a namespace
     *
     * @return ExtensionInterface An extension instance
     *
     * @throws \LogicException if the extension is not registered
     *
     * @api
     */
    public function getExtension($name)
    {
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
        }

        if (isset($this->extensionsByNs[$name])) {
            return $this->extensionsByNs[$name];
        }

        throw new \LogicException(sprintf('Container extension "%s" is not registered', $name));
    }

    /**
     * Returns all registered extensions.
     *
     * @return array An array of ExtensionInterface
     *
     * @api
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Checks if we have an extension.
     *
     * @param string $name The name of the extension
     *
     * @return Boolean If the extension exists
     *
     * @api
     */
    public function hasExtension($name)
    {
        return isset($this->extensions[$name]) || isset($this->extensionsByNs[$name]);
    }

    /**
     * Returns an array of resources loaded to build this configuration.
     *
     * @return ResourceInterface[] An array of resources
     *
     * @api
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
     * @return ContainerBuilder The current instance
     *
     * @api
     */
    public function addResource(ResourceInterface $resource)
    {
        $this->resources[] = $resource;

        return $this;
    }

    /**
     * Adds the object class hierarchy as resources.
     *
     * @param object $object An object instance
     *
     * @api
     */
    public function addObjectResource($object)
    {
        $parent = new \ReflectionObject($object);
        do {
            $this->addResource(new FileResource($parent->getFileName()));
        } while ($parent = $parent->getParentClass());
    }

    /**
     * Loads the configuration for an extension.
     *
     * @param string $extension The extension alias or namespace
     * @param array  $values    An array of values that customizes the extension
     *
     * @return ContainerBuilder The current instance
     *
     * @throws \LogicException if the container is frozen
     *
     * @api
     */
    public function loadFromExtension($extension, array $values = array())
    {
        if (true === $this->isFrozen()) {
            throw new \LogicException('Cannot load from an extension on a frozen container.');
        }

        $namespace = $this->getExtension($extension)->getAlias();

        $this->extensionConfigs[$namespace][] = $values;

        return $this;
    }

    /**
     * Adds a compiler pass.
     *
     * @param CompilerPassInterface $pass A compiler pass
     * @param string                $type The type of compiler pass
     *
     * @api
     */
    public function addCompilerPass(CompilerPassInterface $pass, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION)
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        $this->compiler->addPass($pass, $type);

        $this->addObjectResource($pass);
    }

    /**
     * Returns the compiler pass config which can then be modified.
     *
     * @return PassConfig The compiler pass config
     *
     * @api
     */
    public function getCompilerPassConfig()
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        return $this->compiler->getPassConfig();
    }

    /**
     * Returns the compiler.
     *
     * @return Compiler The compiler
     *
     * @api
     */
    public function getCompiler()
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        return $this->compiler;
    }

    /**
     * Returns all Scopes.
     *
     * @return array An array of scopes
     *
     * @api
     */
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * Returns all Scope children.
     *
     * @return array An array of scope children.
     *
     * @api
     */
    public function getScopeChildren()
    {
        return $this->scopeChildren;
    }

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     * @param string $scope   The scope
     *
     * @throws \BadMethodCallException if the container is frozen
     *
     * @api
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        if ($this->isFrozen()) {
            throw new \BadMethodCallException('Setting service on a frozen container is not allowed');
        }

        $id = strtolower($id);

        unset($this->definitions[$id], $this->aliases[$id]);

        parent::set($id, $service, $scope);
    }

    /**
     * Removes a service definition.
     *
     * @param string $id The service identifier
     *
     * @api
     */
    public function removeDefinition($id)
    {
        unset($this->definitions[strtolower($id)]);
    }

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return Boolean true if the service is defined, false otherwise
     *
     * @api
     */
    public function has($id)
    {
        $id = strtolower($id);

        return isset($this->definitions[$id]) || isset($this->aliases[$id]) || parent::has($id);
    }

    /**
     * Gets a service.
     *
     * @param string  $id              The service identifier
     * @param integer $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws \InvalidArgumentException if the service is not defined
     * @throws \LogicException if the service has a circular reference to itself
     *
     * @see Reference
     *
     * @api
     */
    public function get($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $id = strtolower($id);

        try {
            return parent::get($id, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
        } catch (\InvalidArgumentException $e) {
            if (isset($this->loading[$id])) {
                throw new \LogicException(sprintf('The service "%s" has a circular reference to itself.', $id), 0, $e);
            }

            if (!$this->hasDefinition($id) && isset($this->aliases[$id])) {
                return $this->get($this->aliases[$id]);
            }

            try {
                $definition = $this->getDefinition($id);
            } catch (\InvalidArgumentException $e) {
                if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                    return null;
                }

                throw $e;
            }

            $this->loading[$id] = true;

            $service = $this->createService($definition, $id);

            unset($this->loading[$id]);

            return $service;
        }
    }

    /**
     * Merges a ContainerBuilder with the current ContainerBuilder configuration.
     *
     * Service definitions overrides the current defined ones.
     *
     * But for parameters, they are overridden by the current ones. It allows
     * the parameters passed to the container constructor to have precedence
     * over the loaded ones.
     *
     * $container = new ContainerBuilder(array('foo' => 'bar'));
     * $loader = new LoaderXXX($container);
     * $loader->load('resource_name');
     * $container->register('foo', new stdClass());
     *
     * In the above example, even if the loaded resource defines a foo
     * parameter, the value will still be 'bar' as defined in the ContainerBuilder
     * constructor.
     *
     * @param ContainerBuilder $container The ContainerBuilder instance to merge.
     *
     * @throws \LogicException when this ContainerBuilder is frozen
     *
     * @api
     */
    public function merge(ContainerBuilder $container)
    {
        if (true === $this->isFrozen()) {
            throw new \LogicException('Cannot merge on a frozen container.');
        }

        $this->addDefinitions($container->getDefinitions());
        $this->addAliases($container->getAliases());
        $this->getParameterBag()->add($container->getParameterBag()->all());

        foreach ($container->getResources() as $resource) {
            $this->addResource($resource);
        }

        foreach ($this->extensions as $name => $extension) {
            if (!isset($this->extensionConfigs[$name])) {
                $this->extensionConfigs[$name] = array();
            }

            $this->extensionConfigs[$name] = array_merge($this->extensionConfigs[$name], $container->getExtensionConfig($name));
        }
    }

    /**
     * Returns the configuration array for the given extension.
     *
     * @param string $name The name of the extension
     *
     * @return array An array of configuration
     *
     * @api
     */
    public function getExtensionConfig($name)
    {
        if (!isset($this->extensionConfigs[$name])) {
            $this->extensionConfigs[$name] = array();
        }

        return $this->extensionConfigs[$name];
    }

    /**
     * Compiles the container.
     *
     * This method passes the container to compiler
     * passes whose job is to manipulate and optimize
     * the container.
     *
     * The main compiler passes roughly do four things:
     *
     *  * The extension configurations are merged;
     *  * Parameter values are resolved;
     *  * The parameter bag is frozen;
     *  * Extension loading is disabled.
     *
     * @api
     */
    public function compile()
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        foreach ($this->compiler->getPassConfig()->getPasses() as $pass) {
            $this->addObjectResource($pass);
        }

        $this->compiler->compile($this);

        $this->extensionConfigs = array();

        parent::compile();
    }

    /**
     * Gets all service ids.
     *
     * @return array An array of all defined service ids
     */
    public function getServiceIds()
    {
        return array_unique(array_merge(array_keys($this->getDefinitions()), array_keys($this->aliases), parent::getServiceIds()));
    }

    /**
     * Adds the service aliases.
     *
     * @param array $aliases An array of aliases
     *
     * @api
     */
    public function addAliases(array $aliases)
    {
        foreach ($aliases as $alias => $id) {
            $this->setAlias($alias, $id);
        }
    }

    /**
     * Sets the service aliases.
     *
     * @param array $aliases An array of service definitions
     *
     * @api
     */
    public function setAliases(array $aliases)
    {
        $this->aliases = array();
        $this->addAliases($aliases);
    }

    /**
     * Sets an alias for an existing service.
     *
     * @param string        $alias The alias to create
     * @param string|Alias  $id    The service to alias
     *
     * @throws \InvalidArgumentException if the id is not a string or an Alias
     * @throws \InvalidArgumentException if the alias is for itself
     *
     * @api
     */
    public function setAlias($alias, $id)
    {
        $alias = strtolower($alias);

        if (is_string($id)) {
            $id = new Alias($id);
        } elseif (!$id instanceof Alias) {
            throw new \InvalidArgumentException('$id must be a string, or an Alias object.');
        }

        if ($alias === strtolower($id)) {
            throw new \InvalidArgumentException('An alias can not reference itself, got a circular reference on "'.$alias.'".');
        }

        unset($this->definitions[$alias]);

        $this->aliases[$alias] = $id;
    }

    /**
     * Removes an alias.
     *
     * @param string $alias The alias to remove
     *
     * @api
     */
    public function removeAlias($alias)
    {
        unset($this->aliases[strtolower($alias)]);
    }

    /**
     * Returns true if an alias exists under the given identifier.
     *
     * @param string $id The service identifier
     *
     * @return Boolean true if the alias exists, false otherwise
     *
     * @api
     */
    public function hasAlias($id)
    {
        return isset($this->aliases[strtolower($id)]);
    }

    /**
     * Gets all defined aliases.
     *
     * @return Alias[] An array of aliases
     *
     * @api
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Gets an alias.
     *
     * @param string $id The service identifier
     *
     * @return Alias An Alias instance
     *
     * @throws \InvalidArgumentException if the alias does not exist
     *
     * @api
     */
    public function getAlias($id)
    {
        $id = strtolower($id);

        if (!$this->hasAlias($id)) {
            throw new \InvalidArgumentException(sprintf('The service alias "%s" does not exist.', $id));
        }

        return $this->aliases[$id];
    }

    /**
     * Registers a service definition.
     *
     * This methods allows for simple registration of service definition
     * with a fluid interface.
     *
     * @param string $id    The service identifier
     * @param string $class The service class
     *
     * @return Definition A Definition instance
     *
     * @api
     */
    public function register($id, $class = null)
    {
        return $this->setDefinition(strtolower($id), new Definition($class));
    }

    /**
     * Adds the service definitions.
     *
     * @param Definition[] $definitions An array of service definitions
     *
     * @api
     */
    public function addDefinitions(array $definitions)
    {
        foreach ($definitions as $id => $definition) {
            $this->setDefinition($id, $definition);
        }
    }

    /**
     * Sets the service definitions.
     *
     * @param array $definitions An array of service definitions
     *
     * @api
     */
    public function setDefinitions(array $definitions)
    {
        $this->definitions = array();
        $this->addDefinitions($definitions);
    }

    /**
     * Gets all service definitions.
     *
     * @return Definition[] An array of Definition instances
     *
     * @api
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Sets a service definition.
     *
     * @param string     $id         The service identifier
     * @param Definition $definition A Definition instance
     *
     * @return Definition the service definition
     *
     * @throws \BadMethodCallException if the container is frozen
     *
     * @api
     */
    public function setDefinition($id, Definition $definition)
    {
        if ($this->isFrozen()) {
            throw new \BadMethodCallException('Adding definition to a frozen container is not allowed');
        }

        $id = strtolower($id);

        unset($this->aliases[$id]);

        return $this->definitions[$id] = $definition;
    }

    /**
     * Returns true if a service definition exists under the given identifier.
     *
     * @param string $id The service identifier
     *
     * @return Boolean true if the service definition exists, false otherwise
     *
     * @api
     */
    public function hasDefinition($id)
    {
        return array_key_exists(strtolower($id), $this->definitions);
    }

    /**
     * Gets a service definition.
     *
     * @param string $id The service identifier
     *
     * @return Definition A Definition instance
     *
     * @throws \InvalidArgumentException if the service definition does not exist
     *
     * @api
     */
    public function getDefinition($id)
    {
        $id = strtolower($id);

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
     * @param string $id The service identifier or alias
     *
     * @return Definition A Definition instance
     *
     * @throws \InvalidArgumentException if the service definition does not exist
     *
     * @api
     */
    public function findDefinition($id)
    {
        while ($this->hasAlias($id)) {
            $id = (string) $this->getAlias($id);
        }

        return $this->getDefinition($id);
    }

    /**
     * Creates a service for a service definition.
     *
     * @param Definition $definition A service definition instance
     * @param string     $id         The service identifier
     *
     * @return object The service described by the service definition
     *
     * @throws \RuntimeException When the scope is inactive
     * @throws \RuntimeException When the factory definition is incomplete
     * @throws \InvalidArgumentException When configure callable is not callable
     */
    private function createService(Definition $definition, $id)
    {
        if ($definition->isSynthetic()) {
            throw new \RuntimeException(sprintf('You have requested a synthetic service ("%s"). The DIC does not know how to construct this service.', $id));
        }

        if (null !== $definition->getFile()) {
            require_once $this->getParameterBag()->resolveValue($definition->getFile());
        }

        $arguments = $this->resolveServices($this->getParameterBag()->resolveValue($definition->getArguments()));

        if (null !== $definition->getFactoryMethod()) {
            if (null !== $definition->getFactoryClass()) {
                $factory = $this->getParameterBag()->resolveValue($definition->getFactoryClass());
            } elseif (null !== $definition->getFactoryService()) {
                $factory = $this->get($this->getParameterBag()->resolveValue($definition->getFactoryService()));
            } else {
                throw new \RuntimeException('Cannot create service from factory method without a factory service or factory class.');
            }

            $service = call_user_func_array(array($factory, $definition->getFactoryMethod()), $arguments);
        } else {
            $r = new \ReflectionClass($this->getParameterBag()->resolveValue($definition->getClass()));

            $service = null === $r->getConstructor() ? $r->newInstance() : $r->newInstanceArgs($arguments);
        }

        if (self::SCOPE_PROTOTYPE !== $scope = $definition->getScope()) {
            if (self::SCOPE_CONTAINER !== $scope && !isset($this->scopedServices[$scope])) {
                throw new \RuntimeException('You tried to create a service of an inactive scope.');
            }

            $this->services[$lowerId = strtolower($id)] = $service;

            if (self::SCOPE_CONTAINER !== $scope) {
                $this->scopedServices[$scope][$lowerId] = $service;
            }
        }

        foreach ($definition->getMethodCalls() as $call) {
            $services = self::getServiceConditionals($call[1]);

            $ok = true;
            foreach ($services as $s) {
                if (!$this->has($s)) {
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                call_user_func_array(array($service, $call[0]), $this->resolveServices($this->getParameterBag()->resolveValue($call[1])));
            }
        }

        $properties = $this->resolveServices($this->getParameterBag()->resolveValue($definition->getProperties()));
        foreach ($properties as $name => $value) {
            $service->$name = $value;
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable) && is_object($callable[0]) && $callable[0] instanceof Reference) {
                $callable[0] = $this->get((string) $callable[0]);
            } elseif (is_array($callable)) {
                $callable[0] = $this->getParameterBag()->resolveValue($callable[0]);
            }

            if (!is_callable($callable)) {
                throw new \InvalidArgumentException(sprintf('The configure callable for class "%s" is not a callable.', get_class($service)));
            }

            call_user_func($callable, $service);
        }

        return $service;
    }

    /**
     * Replaces service references by the real service instance.
     *
     * @param mixed $value A value
     *
     * @return mixed The same value with all service references replaced by the real service instances
     */
    public function resolveServices($value)
    {
        if (is_array($value)) {
            foreach ($value as &$v) {
                $v = $this->resolveServices($v);
            }
        } elseif (is_object($value) && $value instanceof Reference) {
            $value = $this->get((string) $value, $value->getInvalidBehavior());
        } elseif (is_object($value) && $value instanceof Definition) {
            $value = $this->createService($value, null);
        }

        return $value;
    }

    /**
     * Returns service ids for a given tag.
     *
     * @param string $name The tag name
     *
     * @return array An array of tags
     *
     * @api
     */
    public function findTaggedServiceIds($name)
    {
        $tags = array();
        foreach ($this->getDefinitions() as $id => $definition) {
            if ($definition->getTag($name)) {
                $tags[$id] = $definition->getTag($name);
            }
        }

        return $tags;
    }

    /**
     * Returns the Service Conditionals.
     *
     * @param mixed $value An array of conditionals to return.
     *
     * @return array An array of Service conditionals
     */
    public static function getServiceConditionals($value)
    {
        $services = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::getServiceConditionals($v)));
            }
        } elseif (is_object($value) && $value instanceof Reference && $value->getInvalidBehavior() === ContainerInterface::IGNORE_ON_INVALID_REFERENCE) {
            $services[] = (string) $value;
        }

        return $services;
    }
}
