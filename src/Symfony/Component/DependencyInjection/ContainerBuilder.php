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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\Compiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\ResolveEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\RealServiceInstantiator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * ContainerBuilder is a DI container that provides an API to easily describe services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerBuilder extends Container implements TaggedContainerInterface
{
    /**
     * @var ExtensionInterface[]
     */
    private $extensions = array();

    /**
     * @var ExtensionInterface[]
     */
    private $extensionsByNs = array();

    /**
     * @var Definition[]
     */
    private $definitions = array();

    /**
     * @var Alias[]
     */
    private $aliasDefinitions = array();

    /**
     * @var ResourceInterface[]
     */
    private $resources = array();

    private $extensionConfigs = array();

    /**
     * @var Compiler
     */
    private $compiler;

    private $trackResources;

    /**
     * @var InstantiatorInterface|null
     */
    private $proxyInstantiator;

    /**
     * @var ExpressionLanguage|null
     */
    private $expressionLanguage;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = array();

    /**
     * @var string[] with tag names used by findTaggedServiceIds
     */
    private $usedTags = array();

    /**
     * @var string[][] a map of env var names to their placeholders
     */
    private $envPlaceholders = array();

    /**
     * @var int[] a map of env vars to their resolution counter
     */
    private $envCounters = array();

    /**
     * @var string[] the list of vendor directories
     */
    private $vendors;

    private $autoconfiguredInstanceof = array();

    private $removedIds = array();
    private $alreadyLoading = array();

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        parent::__construct($parameterBag);

        $this->trackResources = interface_exists('Symfony\Component\Config\Resource\ResourceInterface');
        $this->setDefinition('service_container', (new Definition(ContainerInterface::class))->setSynthetic(true)->setPublic(true));
        $this->setAlias(PsrContainerInterface::class, new Alias('service_container', false));
        $this->setAlias(ContainerInterface::class, new Alias('service_container', false));
    }

    /**
     * @var \ReflectionClass[] a list of class reflectors
     */
    private $classReflectors;

    /**
     * Sets the track resources flag.
     *
     * If you are not using the loaders and therefore don't want
     * to depend on the Config component, set this flag to false.
     *
     * @param bool $track True if you want to track resources, false otherwise
     */
    public function setResourceTracking($track)
    {
        $this->trackResources = (bool) $track;
    }

    /**
     * Checks if resources are tracked.
     *
     * @return bool true If resources are tracked, false otherwise
     */
    public function isTrackingResources()
    {
        return $this->trackResources;
    }

    /**
     * Sets the instantiator to be used when fetching proxies.
     */
    public function setProxyInstantiator(InstantiatorInterface $proxyInstantiator)
    {
        $this->proxyInstantiator = $proxyInstantiator;
    }

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
     * @throws LogicException if the extension is not registered
     */
    public function getExtension($name)
    {
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
        }

        if (isset($this->extensionsByNs[$name])) {
            return $this->extensionsByNs[$name];
        }

        throw new LogicException(sprintf('Container extension "%s" is not registered', $name));
    }

    /**
     * Returns all registered extensions.
     *
     * @return ExtensionInterface[] An array of ExtensionInterface
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
     * @return bool If the extension exists
     */
    public function hasExtension($name)
    {
        return isset($this->extensions[$name]) || isset($this->extensionsByNs[$name]);
    }

    /**
     * Returns an array of resources loaded to build this configuration.
     *
     * @return ResourceInterface[] An array of resources
     */
    public function getResources()
    {
        return array_values($this->resources);
    }

    /**
     * @return $this
     */
    public function addResource(ResourceInterface $resource)
    {
        if (!$this->trackResources) {
            return $this;
        }

        if ($resource instanceof GlobResource && $this->inVendors($resource->getPrefix())) {
            return $this;
        }

        $this->resources[(string) $resource] = $resource;

        return $this;
    }

    /**
     * Sets the resources for this configuration.
     *
     * @param ResourceInterface[] $resources An array of resources
     *
     * @return $this
     */
    public function setResources(array $resources)
    {
        if (!$this->trackResources) {
            return $this;
        }

        $this->resources = $resources;

        return $this;
    }

    /**
     * Adds the object class hierarchy as resources.
     *
     * @param object|string $object An object instance or class name
     *
     * @return $this
     */
    public function addObjectResource($object)
    {
        if ($this->trackResources) {
            if (is_object($object)) {
                $object = get_class($object);
            }
            if (!isset($this->classReflectors[$object])) {
                $this->classReflectors[$object] = new \ReflectionClass($object);
            }
            $class = $this->classReflectors[$object];

            foreach ($class->getInterfaceNames() as $name) {
                if (null === $interface = &$this->classReflectors[$name]) {
                    $interface = new \ReflectionClass($name);
                }
                $file = $interface->getFileName();
                if (false !== $file && file_exists($file)) {
                    $this->fileExists($file);
                }
            }
            do {
                $file = $class->getFileName();
                if (false !== $file && file_exists($file)) {
                    $this->fileExists($file);
                }
                foreach ($class->getTraitNames() as $name) {
                    $this->addObjectResource($name);
                }
            } while ($class = $class->getParentClass());
        }

        return $this;
    }

    /**
     * Retrieves the requested reflection class and registers it for resource tracking.
     *
     * @throws \ReflectionException when a parent class/interface/trait is not found and $throw is true
     *
     * @final
     */
    public function getReflectionClass(?string $class, bool $throw = true): ?\ReflectionClass
    {
        if (!$class = $this->getParameterBag()->resolveValue($class)) {
            return null;
        }
        $resource = null;

        try {
            if (isset($this->classReflectors[$class])) {
                $classReflector = $this->classReflectors[$class];
            } elseif ($this->trackResources) {
                $resource = new ClassExistenceResource($class, false);
                $classReflector = $resource->isFresh(0) ? false : new \ReflectionClass($class);
            } else {
                $classReflector = new \ReflectionClass($class);
            }
        } catch (\ReflectionException $e) {
            if ($throw) {
                throw $e;
            }
            $classReflector = false;
        }

        if ($this->trackResources) {
            if (!$classReflector) {
                $this->addResource($resource ?: new ClassExistenceResource($class, false));
            } elseif (!$classReflector->isInternal()) {
                $path = $classReflector->getFileName();

                if (!$this->inVendors($path)) {
                    $this->addResource(new ReflectionClassResource($classReflector, $this->vendors));
                }
            }
            $this->classReflectors[$class] = $classReflector;
        }

        return $classReflector ?: null;
    }

    /**
     * Checks whether the requested file or directory exists and registers the result for resource tracking.
     *
     * @param string      $path          The file or directory path for which to check the existence
     * @param bool|string $trackContents Whether to track contents of the given resource. If a string is passed,
     *                                   it will be used as pattern for tracking contents of the requested directory
     *
     * @return bool
     *
     * @final
     */
    public function fileExists(string $path, $trackContents = true): bool
    {
        $exists = file_exists($path);

        if (!$this->trackResources || $this->inVendors($path)) {
            return $exists;
        }

        if (!$exists) {
            $this->addResource(new FileExistenceResource($path));

            return $exists;
        }

        if (is_dir($path)) {
            if ($trackContents) {
                $this->addResource(new DirectoryResource($path, is_string($trackContents) ? $trackContents : null));
            } else {
                $this->addResource(new GlobResource($path, '/*', false));
            }
        } elseif ($trackContents) {
            $this->addResource(new FileResource($path));
        }

        return $exists;
    }

    /**
     * Loads the configuration for an extension.
     *
     * @param string $extension The extension alias or namespace
     * @param array  $values    An array of values that customizes the extension
     *
     * @return $this
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     * @throws \LogicException        if the extension is not registered
     */
    public function loadFromExtension($extension, array $values = null)
    {
        if ($this->isCompiled()) {
            throw new BadMethodCallException('Cannot load from an extension on a compiled container.');
        }

        if (func_num_args() < 2) {
            $values = array();
        }

        $namespace = $this->getExtension($extension)->getAlias();

        $this->extensionConfigs[$namespace][] = $values;

        return $this;
    }

    /**
     * Adds a compiler pass.
     *
     * @param CompilerPassInterface $pass     A compiler pass
     * @param string                $type     The type of compiler pass
     * @param int                   $priority Used to sort the passes
     *
     * @return $this
     */
    public function addCompilerPass(CompilerPassInterface $pass, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0)
    {
        $this->getCompiler()->addPass($pass, $type, $priority);

        $this->addObjectResource($pass);

        return $this;
    }

    /**
     * Returns the compiler pass config which can then be modified.
     *
     * @return PassConfig The compiler pass config
     */
    public function getCompilerPassConfig()
    {
        return $this->getCompiler()->getPassConfig();
    }

    /**
     * Returns the compiler.
     *
     * @return Compiler The compiler
     */
    public function getCompiler()
    {
        if (null === $this->compiler) {
            $this->compiler = new Compiler();
        }

        return $this->compiler;
    }

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     *
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function set($id, $service)
    {
        $id = (string) $id;

        if ($this->isCompiled() && (isset($this->definitions[$id]) && !$this->definitions[$id]->isSynthetic())) {
            // setting a synthetic service on a compiled container is alright
            throw new BadMethodCallException(sprintf('Setting service "%s" for an unknown or non-synthetic service definition on a compiled container is not allowed.', $id));
        }

        unset($this->definitions[$id], $this->aliasDefinitions[$id], $this->removedIds[$id]);

        parent::set($id, $service);
    }

    /**
     * Removes a service definition.
     *
     * @param string $id The service identifier
     */
    public function removeDefinition($id)
    {
        if (isset($this->definitions[$id = (string) $id])) {
            unset($this->definitions[$id]);
            $this->removedIds[$id] = true;
        }
    }

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service is defined, false otherwise
     */
    public function has($id)
    {
        $id = (string) $id;

        return isset($this->definitions[$id]) || isset($this->aliasDefinitions[$id]) || parent::has($id);
    }

    /**
     * Gets a service.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws InvalidArgumentException          when no definitions are available
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     * @throws \Exception
     *
     * @see Reference
     */
    public function get($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if ($this->isCompiled() && isset($this->removedIds[$id = (string) $id]) && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior) {
            return parent::get($id);
        }

        return $this->doGet($id, $invalidBehavior);
    }

    private function doGet($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, array &$inlineServices = array())
    {
        if (isset($inlineServices[$id])) {
            return $inlineServices[$id];
        }
        if (ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $invalidBehavior) {
            return parent::get($id, $invalidBehavior);
        }
        if ($service = parent::get($id, ContainerInterface::NULL_ON_INVALID_REFERENCE)) {
            return $service;
        }

        if (!isset($this->definitions[$id]) && isset($this->aliasDefinitions[$id])) {
            return $this->doGet((string) $this->aliasDefinitions[$id], $invalidBehavior, $inlineServices);
        }

        try {
            $definition = $this->getDefinition($id);
        } catch (ServiceNotFoundException $e) {
            if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                return;
            }

            throw $e;
        }

        $loading = isset($this->alreadyLoading[$id]) ? 'loading' : 'alreadyLoading';
        $this->{$loading}[$id] = true;

        try {
            $service = $this->createService($definition, $inlineServices, $id);
        } finally {
            unset($this->{$loading}[$id]);
        }

        return $service;
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
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function merge(self $container)
    {
        if ($this->isCompiled()) {
            throw new BadMethodCallException('Cannot merge on a compiled container.');
        }

        $this->addDefinitions($container->getDefinitions());
        $this->addAliases($container->getAliases());
        $this->getParameterBag()->add($container->getParameterBag()->all());

        if ($this->trackResources) {
            foreach ($container->getResources() as $resource) {
                $this->addResource($resource);
            }
        }

        foreach ($this->extensions as $name => $extension) {
            if (!isset($this->extensionConfigs[$name])) {
                $this->extensionConfigs[$name] = array();
            }

            $this->extensionConfigs[$name] = array_merge($this->extensionConfigs[$name], $container->getExtensionConfig($name));
        }

        if ($this->getParameterBag() instanceof EnvPlaceholderParameterBag && $container->getParameterBag() instanceof EnvPlaceholderParameterBag) {
            $envPlaceholders = $container->getParameterBag()->getEnvPlaceholders();
            $this->getParameterBag()->mergeEnvPlaceholders($container->getParameterBag());
        } else {
            $envPlaceholders = array();
        }

        foreach ($container->envCounters as $env => $count) {
            if (!$count && !isset($envPlaceholders[$env])) {
                continue;
            }
            if (!isset($this->envCounters[$env])) {
                $this->envCounters[$env] = $count;
            } else {
                $this->envCounters[$env] += $count;
            }
        }

        foreach ($container->getAutoconfiguredInstanceof() as $interface => $childDefinition) {
            if (isset($this->autoconfiguredInstanceof[$interface])) {
                throw new InvalidArgumentException(sprintf('"%s" has already been autoconfigured and merge() does not support merging autoconfiguration for the same class/interface.', $interface));
            }

            $this->autoconfiguredInstanceof[$interface] = $childDefinition;
        }
    }

    /**
     * Returns the configuration array for the given extension.
     *
     * @param string $name The name of the extension
     *
     * @return array An array of configuration
     */
    public function getExtensionConfig($name)
    {
        if (!isset($this->extensionConfigs[$name])) {
            $this->extensionConfigs[$name] = array();
        }

        return $this->extensionConfigs[$name];
    }

    /**
     * Prepends a config array to the configs of the given extension.
     *
     * @param string $name   The name of the extension
     * @param array  $config The config to set
     */
    public function prependExtensionConfig($name, array $config)
    {
        if (!isset($this->extensionConfigs[$name])) {
            $this->extensionConfigs[$name] = array();
        }

        array_unshift($this->extensionConfigs[$name], $config);
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
     * @param bool $resolveEnvPlaceholders Whether %env()% parameters should be resolved using the current
     *                                     env vars or be replaced by uniquely identifiable placeholders.
     *                                     Set to "true" when you want to use the current ContainerBuilder
     *                                     directly, keep to "false" when the container is dumped instead.
     */
    public function compile(bool $resolveEnvPlaceholders = false)
    {
        $compiler = $this->getCompiler();

        if ($this->trackResources) {
            foreach ($compiler->getPassConfig()->getPasses() as $pass) {
                $this->addObjectResource($pass);
            }
        }
        $bag = $this->getParameterBag();

        if ($resolveEnvPlaceholders && $bag instanceof EnvPlaceholderParameterBag) {
            $compiler->addPass(new ResolveEnvPlaceholdersPass(), PassConfig::TYPE_AFTER_REMOVING, -1000);
        }

        $compiler->compile($this);

        foreach ($this->definitions as $id => $definition) {
            if ($this->trackResources && $definition->isLazy()) {
                $this->getReflectionClass($definition->getClass());
            }
        }

        $this->extensionConfigs = array();

        if ($bag instanceof EnvPlaceholderParameterBag) {
            if ($resolveEnvPlaceholders) {
                $this->parameterBag = new ParameterBag($this->resolveEnvPlaceholders($bag->all(), true));
            }

            $this->envPlaceholders = $bag->getEnvPlaceholders();
        }

        parent::compile();

        foreach ($this->definitions + $this->aliasDefinitions as $id => $definition) {
            if (!$definition->isPublic() || $definition->isPrivate()) {
                $this->removedIds[$id] = true;
            }
        }
    }

    /**
     * Gets all service ids.
     *
     * @return array An array of all defined service ids
     */
    public function getServiceIds()
    {
        return array_unique(array_merge(array_keys($this->getDefinitions()), array_keys($this->aliasDefinitions), parent::getServiceIds()));
    }

    /**
     * Gets removed service or alias ids.
     *
     * @return array
     */
    public function getRemovedIds()
    {
        return $this->removedIds;
    }

    /**
     * Adds the service aliases.
     */
    public function addAliases(array $aliases)
    {
        foreach ($aliases as $alias => $id) {
            $this->setAlias($alias, $id);
        }
    }

    /**
     * Sets the service aliases.
     */
    public function setAliases(array $aliases)
    {
        $this->aliasDefinitions = array();
        $this->addAliases($aliases);
    }

    /**
     * Sets an alias for an existing service.
     *
     * @param string       $alias The alias to create
     * @param string|Alias $id    The service to alias
     *
     * @return Alias
     *
     * @throws InvalidArgumentException if the id is not a string or an Alias
     * @throws InvalidArgumentException if the alias is for itself
     */
    public function setAlias($alias, $id)
    {
        $alias = (string) $alias;

        if (is_string($id)) {
            $id = new Alias($id);
        } elseif (!$id instanceof Alias) {
            throw new InvalidArgumentException('$id must be a string, or an Alias object.');
        }

        if ($alias === (string) $id) {
            throw new InvalidArgumentException(sprintf('An alias can not reference itself, got a circular reference on "%s".', $alias));
        }

        unset($this->definitions[$alias], $this->removedIds[$alias]);

        return $this->aliasDefinitions[$alias] = $id;
    }

    /**
     * Removes an alias.
     *
     * @param string $alias The alias to remove
     */
    public function removeAlias($alias)
    {
        if (isset($this->aliasDefinitions[$alias = (string) $alias])) {
            unset($this->aliasDefinitions[$alias]);
            $this->removedIds[$alias] = true;
        }
    }

    /**
     * Returns true if an alias exists under the given identifier.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the alias exists, false otherwise
     */
    public function hasAlias($id)
    {
        return isset($this->aliasDefinitions[$id = (string) $id]);
    }

    /**
     * Gets all defined aliases.
     *
     * @return Alias[] An array of aliases
     */
    public function getAliases()
    {
        return $this->aliasDefinitions;
    }

    /**
     * Gets an alias.
     *
     * @param string $id The service identifier
     *
     * @return Alias An Alias instance
     *
     * @throws InvalidArgumentException if the alias does not exist
     */
    public function getAlias($id)
    {
        $id = (string) $id;

        if (!isset($this->aliasDefinitions[$id])) {
            throw new InvalidArgumentException(sprintf('The service alias "%s" does not exist.', $id));
        }

        return $this->aliasDefinitions[$id];
    }

    /**
     * Registers a service definition.
     *
     * This methods allows for simple registration of service definition
     * with a fluid interface.
     *
     * @param string $id         The service identifier
     * @param string $class|null The service class
     *
     * @return Definition A Definition instance
     */
    public function register($id, $class = null)
    {
        return $this->setDefinition($id, new Definition($class));
    }

    /**
     * Registers an autowired service definition.
     *
     * This method implements a shortcut for using setDefinition() with
     * an autowired definition.
     *
     * @param string      $id    The service identifier
     * @param null|string $class The service class
     *
     * @return Definition The created definition
     */
    public function autowire($id, $class = null)
    {
        return $this->setDefinition($id, (new Definition($class))->setAutowired(true));
    }

    /**
     * Adds the service definitions.
     *
     * @param Definition[] $definitions An array of service definitions
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
     * @param Definition[] $definitions An array of service definitions
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
     * @throws BadMethodCallException When this ContainerBuilder is compiled
     */
    public function setDefinition($id, Definition $definition)
    {
        if ($this->isCompiled()) {
            throw new BadMethodCallException('Adding definition to a compiled container is not allowed');
        }

        $id = (string) $id;

        unset($this->aliasDefinitions[$id], $this->removedIds[$id]);

        return $this->definitions[$id] = $definition;
    }

    /**
     * Returns true if a service definition exists under the given identifier.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service definition exists, false otherwise
     */
    public function hasDefinition($id)
    {
        return isset($this->definitions[(string) $id]);
    }

    /**
     * Gets a service definition.
     *
     * @param string $id The service identifier
     *
     * @return Definition A Definition instance
     *
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    public function getDefinition($id)
    {
        $id = (string) $id;

        if (!isset($this->definitions[$id])) {
            throw new ServiceNotFoundException($id);
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
     * @throws ServiceNotFoundException if the service definition does not exist
     */
    public function findDefinition($id)
    {
        $id = (string) $id;

        $seen = array();
        while (isset($this->aliasDefinitions[$id])) {
            $id = (string) $this->aliasDefinitions[$id];

            if (isset($seen[$id])) {
                $seen = array_values($seen);
                $seen = array_slice($seen, array_search($id, $seen));
                $seen[] = $id;

                throw new ServiceCircularReferenceException($id, $seen);
            }

            $seen[$id] = $id;
        }

        return $this->getDefinition($id);
    }

    /**
     * Creates a service for a service definition.
     *
     * @param Definition $definition A service definition instance
     * @param string     $id         The service identifier
     * @param bool       $tryProxy   Whether to try proxying the service with a lazy proxy
     *
     * @return object The service described by the service definition
     *
     * @throws RuntimeException         When the factory definition is incomplete
     * @throws RuntimeException         When the service is a synthetic service
     * @throws InvalidArgumentException When configure callable is not callable
     */
    private function createService(Definition $definition, array &$inlineServices, $id = null, $tryProxy = true)
    {
        if (null === $id && isset($inlineServices[$h = spl_object_hash($definition)])) {
            return $inlineServices[$h];
        }

        if ($definition instanceof ChildDefinition) {
            throw new RuntimeException(sprintf('Constructing service "%s" from a parent definition is not supported at build time.', $id));
        }

        if ($definition->isSynthetic()) {
            throw new RuntimeException(sprintf('You have requested a synthetic service ("%s"). The DIC does not know how to construct this service.', $id));
        }

        if ($definition->isDeprecated()) {
            @trigger_error($definition->getDeprecationMessage($id), E_USER_DEPRECATED);
        }

        if ($tryProxy && $definition->isLazy()) {
            $proxy = $this
                ->getProxyInstantiator()
                ->instantiateProxy(
                    $this,
                    $definition,
                    $id, function () use ($definition, &$inlineServices, $id) {
                        return $this->createService($definition, $inlineServices, $id, false);
                    }
                );
            $this->shareService($definition, $proxy, $id, $inlineServices);

            return $proxy;
        }

        $parameterBag = $this->getParameterBag();

        if (null !== $definition->getFile()) {
            require_once $parameterBag->resolveValue($definition->getFile());
        }

        $arguments = $this->doResolveServices($parameterBag->unescapeValue($parameterBag->resolveValue($definition->getArguments())), $inlineServices);

        if (null !== $id && $definition->isShared() && isset($this->services[$id]) && ($tryProxy || !$definition->isLazy())) {
            return $this->services[$id];
        }

        if (null !== $factory = $definition->getFactory()) {
            if (is_array($factory)) {
                $factory = array($this->doResolveServices($parameterBag->resolveValue($factory[0]), $inlineServices), $factory[1]);
            } elseif (!is_string($factory)) {
                throw new RuntimeException(sprintf('Cannot create service "%s" because of invalid factory', $id));
            }

            $service = call_user_func_array($factory, $arguments);

            if (!$definition->isDeprecated() && is_array($factory) && is_string($factory[0])) {
                $r = new \ReflectionClass($factory[0]);

                if (0 < strpos($r->getDocComment(), "\n * @deprecated ")) {
                    @trigger_error(sprintf('The "%s" service relies on the deprecated "%s" factory class. It should either be deprecated or its factory upgraded.', $id, $r->name), E_USER_DEPRECATED);
                }
            }
        } else {
            $r = new \ReflectionClass($class = $parameterBag->resolveValue($definition->getClass()));

            $service = null === $r->getConstructor() ? $r->newInstance() : $r->newInstanceArgs($arguments);

            if (!$definition->isDeprecated() && 0 < strpos($r->getDocComment(), "\n * @deprecated ")) {
                @trigger_error(sprintf('The "%s" service relies on the deprecated "%s" class. It should either be deprecated or its implementation upgraded.', $id, $r->name), E_USER_DEPRECATED);
            }
        }

        if ($tryProxy || !$definition->isLazy()) {
            // share only if proxying failed, or if not a proxy
            $this->shareService($definition, $service, $id, $inlineServices);
        }

        $properties = $this->doResolveServices($parameterBag->unescapeValue($parameterBag->resolveValue($definition->getProperties())), $inlineServices);
        foreach ($properties as $name => $value) {
            $service->$name = $value;
        }

        foreach ($definition->getMethodCalls() as $call) {
            $this->callMethod($service, $call, $inlineServices);
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable)) {
                $callable[0] = $parameterBag->resolveValue($callable[0]);

                if ($callable[0] instanceof Reference) {
                    $callable[0] = $this->doGet((string) $callable[0], $callable[0]->getInvalidBehavior(), $inlineServices);
                } elseif ($callable[0] instanceof Definition) {
                    $callable[0] = $this->createService($callable[0], $inlineServices);
                }
            }

            if (!is_callable($callable)) {
                throw new InvalidArgumentException(sprintf('The configure callable for class "%s" is not a callable.', get_class($service)));
            }

            call_user_func($callable, $service);
        }

        return $service;
    }

    /**
     * Replaces service references by the real service instance and evaluates expressions.
     *
     * @param mixed $value A value
     *
     * @return mixed The same value with all service references replaced by
     *               the real service instances and all expressions evaluated
     */
    public function resolveServices($value)
    {
        return $this->doResolveServices($value);
    }

    private function doResolveServices($value, array &$inlineServices = array())
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->doResolveServices($v, $inlineServices);
            }
        } elseif ($value instanceof ServiceClosureArgument) {
            $reference = $value->getValues()[0];
            $value = function () use ($reference) {
                return $this->resolveServices($reference);
            };
        } elseif ($value instanceof IteratorArgument) {
            $value = new RewindableGenerator(function () use ($value) {
                foreach ($value->getValues() as $k => $v) {
                    foreach (self::getServiceConditionals($v) as $s) {
                        if (!$this->has($s)) {
                            continue 2;
                        }
                    }
                    foreach (self::getInitializedConditionals($v) as $s) {
                        if (!$this->doGet($s, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)) {
                            continue 2;
                        }
                    }

                    yield $k => $this->resolveServices($v);
                }
            }, function () use ($value) {
                $count = 0;
                foreach ($value->getValues() as $v) {
                    foreach (self::getServiceConditionals($v) as $s) {
                        if (!$this->has($s)) {
                            continue 2;
                        }
                    }
                    foreach (self::getInitializedConditionals($v) as $s) {
                        if (!$this->doGet($s, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)) {
                            continue 2;
                        }
                    }

                    ++$count;
                }

                return $count;
            });
        } elseif ($value instanceof Reference) {
            $value = $this->doGet((string) $value, $value->getInvalidBehavior(), $inlineServices);
        } elseif ($value instanceof Definition) {
            $value = $this->createService($value, $inlineServices);
        } elseif ($value instanceof Parameter) {
            $value = $this->getParameter((string) $value);
        } elseif ($value instanceof Expression) {
            $value = $this->getExpressionLanguage()->evaluate($value, array('container' => $this));
        }

        return $value;
    }

    /**
     * Returns service ids for a given tag.
     *
     * Example:
     *
     * $container->register('foo')->addTag('my.tag', array('hello' => 'world'));
     *
     * $serviceIds = $container->findTaggedServiceIds('my.tag');
     * foreach ($serviceIds as $serviceId => $tags) {
     *     foreach ($tags as $tag) {
     *         echo $tag['hello'];
     *     }
     * }
     *
     * @param string $name
     * @param bool   $throwOnAbstract
     *
     * @return array An array of tags with the tagged service as key, holding a list of attribute arrays
     */
    public function findTaggedServiceIds($name, $throwOnAbstract = false)
    {
        $this->usedTags[] = $name;
        $tags = array();
        foreach ($this->getDefinitions() as $id => $definition) {
            if ($definition->hasTag($name)) {
                if ($throwOnAbstract && $definition->isAbstract()) {
                    throw new InvalidArgumentException(sprintf('The service "%s" tagged "%s" must not be abstract.', $id, $name));
                }
                $tags[$id] = $definition->getTag($name);
            }
        }

        return $tags;
    }

    /**
     * Returns all tags the defined services use.
     *
     * @return array An array of tags
     */
    public function findTags()
    {
        $tags = array();
        foreach ($this->getDefinitions() as $id => $definition) {
            $tags = array_merge(array_keys($definition->getTags()), $tags);
        }

        return array_unique($tags);
    }

    /**
     * Returns all tags not queried by findTaggedServiceIds.
     *
     * @return string[] An array of tags
     */
    public function findUnusedTags()
    {
        return array_values(array_diff($this->findTags(), $this->usedTags));
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * @return ExpressionFunctionProviderInterface[]
     */
    public function getExpressionLanguageProviders()
    {
        return $this->expressionLanguageProviders;
    }

    /**
     * Returns a ChildDefinition that will be used for autoconfiguring the interface/class.
     *
     * @param string $interface The class or interface to match
     *
     * @return ChildDefinition
     */
    public function registerForAutoconfiguration($interface)
    {
        if (!isset($this->autoconfiguredInstanceof[$interface])) {
            $this->autoconfiguredInstanceof[$interface] = new ChildDefinition('');
        }

        return $this->autoconfiguredInstanceof[$interface];
    }

    /**
     * Returns an array of ChildDefinition[] keyed by interface.
     *
     * @return ChildDefinition[]
     */
    public function getAutoconfiguredInstanceof()
    {
        return $this->autoconfiguredInstanceof;
    }

    /**
     * Resolves env parameter placeholders in a string or an array.
     *
     * @param mixed            $value     The value to resolve
     * @param string|true|null $format    A sprintf() format returning the replacement for each env var name or
     *                                    null to resolve back to the original "%env(VAR)%" format or
     *                                    true to resolve to the actual values of the referenced env vars
     * @param array            &$usedEnvs Env vars found while resolving are added to this array
     *
     * @return mixed The value with env parameters resolved if a string or an array is passed
     */
    public function resolveEnvPlaceholders($value, $format = null, array &$usedEnvs = null)
    {
        if (null === $format) {
            $format = '%%env(%s)%%';
        }

        $bag = $this->getParameterBag();
        if (true === $format) {
            $value = $bag->resolveValue($value);
        }

        if (\is_array($value)) {
            $result = array();
            foreach ($value as $k => $v) {
                $result[\is_string($k) ? $this->resolveEnvPlaceholders($k, $format, $usedEnvs) : $k] = $this->resolveEnvPlaceholders($v, $format, $usedEnvs);
            }

            return $result;
        }

        if (!\is_string($value) || 38 > \strlen($value)) {
            return $value;
        }
        $envPlaceholders = $bag instanceof EnvPlaceholderParameterBag ? $bag->getEnvPlaceholders() : $this->envPlaceholders;

        foreach ($envPlaceholders as $env => $placeholders) {
            foreach ($placeholders as $placeholder) {
                if (false !== stripos($value, $placeholder)) {
                    if (true === $format) {
                        $resolved = $bag->escapeValue($this->getEnv($env));
                    } else {
                        $resolved = sprintf($format, $env);
                    }
                    if ($placeholder === $value) {
                        $value = $resolved;
                    } else {
                        if (!is_string($resolved) && !is_numeric($resolved)) {
                            throw new RuntimeException(sprintf('A string value must be composed of strings and/or numbers, but found parameter "env(%s)" of type %s inside string value "%s".', $env, gettype($resolved), $value));
                        }
                        $value = str_ireplace($placeholder, $resolved, $value);
                    }
                    $usedEnvs[$env] = $env;
                    $this->envCounters[$env] = isset($this->envCounters[$env]) ? 1 + $this->envCounters[$env] : 1;
                }
            }
        }

        return $value;
    }

    /**
     * Get statistics about env usage.
     *
     * @return int[] The number of time each env vars has been resolved
     */
    public function getEnvCounters()
    {
        $bag = $this->getParameterBag();
        $envPlaceholders = $bag instanceof EnvPlaceholderParameterBag ? $bag->getEnvPlaceholders() : $this->envPlaceholders;

        foreach ($envPlaceholders as $env => $placeholders) {
            if (!isset($this->envCounters[$env])) {
                $this->envCounters[$env] = 0;
            }
        }

        return $this->envCounters;
    }

    /**
     * @final
     */
    public function log(CompilerPassInterface $pass, string $message)
    {
        $this->getCompiler()->log($pass, $message);
    }

    /**
     * Returns the Service Conditionals.
     *
     * @param mixed $value An array of conditionals to return
     *
     * @return array An array of Service conditionals
     *
     * @internal
     */
    public static function getServiceConditionals($value)
    {
        $services = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::getServiceConditionals($v)));
            }
        } elseif ($value instanceof Reference && ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $value->getInvalidBehavior()) {
            $services[] = (string) $value;
        }

        return $services;
    }

    /**
     * Returns the initialized conditionals.
     *
     * @param mixed $value An array of conditionals to return
     *
     * @return array An array of uninitialized conditionals
     *
     * @internal
     */
    public static function getInitializedConditionals($value)
    {
        $services = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::getInitializedConditionals($v)));
            }
        } elseif ($value instanceof Reference && ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE === $value->getInvalidBehavior()) {
            $services[] = (string) $value;
        }

        return $services;
    }

    /**
     * Computes a reasonably unique hash of a value.
     *
     * @param mixed $value A serializable value
     *
     * @return string
     */
    public static function hash($value)
    {
        $hash = substr(base64_encode(hash('sha256', serialize($value), true)), 0, 7);

        return str_replace(array('/', '+'), array('.', '_'), $hash);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEnv($name)
    {
        $value = parent::getEnv($name);
        $bag = $this->getParameterBag();

        if (!is_string($value) || !$bag instanceof EnvPlaceholderParameterBag) {
            return $value;
        }

        foreach ($bag->getEnvPlaceholders() as $env => $placeholders) {
            if (isset($placeholders[$value])) {
                $bag = new ParameterBag($bag->all());

                return $bag->unescapeValue($bag->get("env($name)"));
            }
        }

        $this->resolving["env($name)"] = true;
        try {
            return $bag->unescapeValue($this->resolveEnvPlaceholders($bag->escapeValue($value), true));
        } finally {
            unset($this->resolving["env($name)"]);
        }
    }

    /**
     * Retrieves the currently set proxy instantiator or instantiates one.
     */
    private function getProxyInstantiator(): InstantiatorInterface
    {
        if (!$this->proxyInstantiator) {
            $this->proxyInstantiator = new RealServiceInstantiator();
        }

        return $this->proxyInstantiator;
    }

    private function callMethod($service, $call, array &$inlineServices)
    {
        foreach (self::getServiceConditionals($call[1]) as $s) {
            if (!$this->has($s)) {
                return;
            }
        }
        foreach (self::getInitializedConditionals($call[1]) as $s) {
            if (!$this->doGet($s, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $inlineServices)) {
                return;
            }
        }

        call_user_func_array(array($service, $call[0]), $this->doResolveServices($this->getParameterBag()->unescapeValue($this->getParameterBag()->resolveValue($call[1])), $inlineServices));
    }

    /**
     * Shares a given service in the container.
     *
     * @param Definition  $definition
     * @param object      $service
     * @param string|null $id
     */
    private function shareService(Definition $definition, $service, $id, array &$inlineServices)
    {
        $inlineServices[null !== $id ? $id : spl_object_hash($definition)] = $service;

        if (null !== $id && $definition->isShared()) {
            $this->services[$id] = $service;
            unset($this->loading[$id], $this->alreadyLoading[$id]);
        }
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
        }

        return $this->expressionLanguage;
    }

    private function inVendors($path)
    {
        if (null === $this->vendors) {
            $resource = new ComposerResource();
            $this->vendors = $resource->getVendors();
            $this->addResource($resource);
        }
        $path = realpath($path) ?: $path;

        foreach ($this->vendors as $vendor) {
            if (0 === strpos($path, $vendor) && false !== strpbrk(substr($path, strlen($vendor), 1), '/'.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
