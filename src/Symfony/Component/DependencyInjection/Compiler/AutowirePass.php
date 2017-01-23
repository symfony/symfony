<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Config\AutowireServiceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Guesses constructor arguments of services definitions and try to instantiate services if necessary.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AutowirePass extends AbstractRecursivePass implements CompilerPassInterface
{
    /**
     * @var ContainerBuilder
     */
    private $reflectionClasses = array();
    private $definedTypes = array();
    private $types;
    private $ambiguousServiceTypes = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $throwingAutoloader = function ($class) { throw new \ReflectionException(sprintf('Class %s does not exist', $class)); };
        spl_autoload_register($throwingAutoloader);

        try {
            parent::process($container);
        } finally {
            spl_autoload_unregister($throwingAutoloader);

            // Free memory and remove circular reference to container
            $this->reflectionClasses = array();
            $this->definedTypes = array();
            $this->types = null;
            $this->ambiguousServiceTypes = array();
        }
    }

    /**
     * Creates a resource to help know if this service has changed.
     *
     * @param \ReflectionClass $reflectionClass
     *
     * @return AutowireServiceResource
     */
    public static function createResourceForClass(\ReflectionClass $reflectionClass)
    {
        $metadata = array();

        if ($constructor = $reflectionClass->getConstructor()) {
            $metadata['__construct'] = self::getResourceMetadataForMethod($constructor);
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!$reflectionMethod->isStatic()) {
                $metadata[$reflectionMethod->name] = self::getResourceMetadataForMethod($reflectionMethod);
            }
        }

        return new AutowireServiceResource($reflectionClass->name, $reflectionClass->getFileName(), $metadata);
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition || !$autowiredMethods = $value->getAutowiredMethods()) {
            return parent::processValue($value, $isRoot);
        }

        if (!$reflectionClass = $this->getReflectionClass($isRoot ? $this->currentId : null, $value)) {
            return parent::processValue($value, $isRoot);
        }

        if ($this->container->isTrackingResources()) {
            $this->container->addResource(static::createResourceForClass($reflectionClass));
        }

        $methodsCalled = array();
        foreach ($value->getMethodCalls() as $methodCall) {
            $methodsCalled[strtolower($methodCall[0])] = true;
        }

        foreach ($this->getMethodsToAutowire($reflectionClass, $autowiredMethods) as $reflectionMethod) {
            if (!isset($methodsCalled[strtolower($reflectionMethod->name)])) {
                $this->autowireMethod($value, $reflectionMethod);
            }
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Gets the list of methods to autowire.
     *
     * @param \ReflectionClass $reflectionClass
     * @param string[]         $configuredAutowiredMethods
     *
     * @return \ReflectionMethod[]
     */
    private function getMethodsToAutowire(\ReflectionClass $reflectionClass, array $configuredAutowiredMethods)
    {
        $found = array();
        $regexList = array();

        // Always try to autowire the constructor
        if (in_array('__construct', $configuredAutowiredMethods, true)) {
            $autowiredMethods = $configuredAutowiredMethods;
        } else {
            $autowiredMethods = array_merge(array('__construct'), $configuredAutowiredMethods);
        }

        foreach ($autowiredMethods as $pattern) {
            $regexList[] = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/i';
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }

            foreach ($regexList as $k => $regex) {
                if (preg_match($regex, $reflectionMethod->name)) {
                    $found[] = $autowiredMethods[$k];
                    yield $reflectionMethod;

                    continue 2;
                }
            }
        }

        if ($notFound = array_diff($configuredAutowiredMethods, $found)) {
            $compiler = $this->container->getCompiler();
            $compiler->addLogMessage($compiler->getLoggingFormatter()->formatUnusedAutowiringPatterns($this, $this->currentId, $notFound));
        }
    }

    /**
     * Autowires the constructor or a setter.
     *
     * @param Definition        $definition
     * @param \ReflectionMethod $reflectionMethod
     *
     * @throws RuntimeException
     */
    private function autowireMethod(Definition $definition, \ReflectionMethod $reflectionMethod)
    {
        if ($isConstructor = $reflectionMethod->isConstructor()) {
            $arguments = $definition->getArguments();
        } else {
            $arguments = array();
        }

        $addMethodCall = false; // Whether the method should be added to the definition as a call or as arguments
        foreach ($reflectionMethod->getParameters() as $index => $parameter) {
            if (array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }

            try {
                if (!$typeHint = $parameter->getClass()) {
                    // no default value? Then fail
                    if (!$parameter->isOptional()) {
                        if ($isConstructor) {
                            throw new RuntimeException(sprintf('Unable to autowire argument index %d ($%s) for the service "%s". If this is an object, give it a type-hint. Otherwise, specify this argument\'s value explicitly.', $index, $parameter->name, $this->currentId));
                        }

                        return;
                    }

                    // specifically pass the default value
                    $arguments[$index] = $parameter->getDefaultValue();

                    continue;
                }

                if (null === $this->types) {
                    $this->populateAvailableTypes();
                }

                if (isset($this->types[$typeHint->name])) {
                    $value = new Reference($this->types[$typeHint->name]);
                    $addMethodCall = true;
                } else {
                    try {
                        $value = $this->createAutowiredDefinition($typeHint);
                        $addMethodCall = true;
                    } catch (RuntimeException $e) {
                        if ($parameter->allowsNull()) {
                            $value = null;
                        } elseif ($parameter->isDefaultValueAvailable()) {
                            $value = $parameter->getDefaultValue();
                        } else {
                            // The exception code is set to 1 if the exception must be thrown even if it's a setter
                            if (1 === $e->getCode() || $isConstructor) {
                                throw $e;
                            }

                            return;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Typehint against a non-existing class

                if (!$parameter->isDefaultValueAvailable()) {
                    if ($isConstructor) {
                        throw new RuntimeException(sprintf('Cannot autowire argument %s for %s because the type-hinted class does not exist (%s).', $index + 1, $definition->getClass(), $e->getMessage()), 0, $e);
                    }

                    return;
                }

                $value = $parameter->getDefaultValue();
            }

            $arguments[$index] = $value;
        }

        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments);

        if ($isConstructor) {
            $definition->setArguments($arguments);
        } elseif ($addMethodCall) {
            $definition->addMethodCall($reflectionMethod->name, $arguments);
        }
    }

    /**
     * Populates the list of available types.
     */
    private function populateAvailableTypes()
    {
        $this->types = array();

        foreach ($this->container->getDefinitions() as $id => $definition) {
            $this->populateAvailableType($id, $definition);
        }
    }

    /**
     * Populates the list of available types for a given definition.
     *
     * @param string     $id
     * @param Definition $definition
     */
    private function populateAvailableType($id, Definition $definition)
    {
        // Never use abstract services
        if ($definition->isAbstract()) {
            return;
        }

        foreach ($definition->getAutowiringTypes() as $type) {
            $this->definedTypes[$type] = true;
            $this->types[$type] = $id;
        }

        if (!$reflectionClass = $this->getReflectionClass($id, $definition)) {
            return;
        }

        foreach ($reflectionClass->getInterfaces() as $reflectionInterface) {
            $this->set($reflectionInterface->name, $id);
        }

        do {
            $this->set($reflectionClass->name, $id);
        } while ($reflectionClass = $reflectionClass->getParentClass());
    }

    /**
     * Associates a type and a service id if applicable.
     *
     * @param string $type
     * @param string $id
     */
    private function set($type, $id)
    {
        if (isset($this->definedTypes[$type])) {
            return;
        }

        // is this already a type/class that is known to match multiple services?
        if (isset($this->ambiguousServiceTypes[$type])) {
            $this->addServiceToAmbiguousType($id, $type);

            return;
        }

        // check to make sure the type doesn't match multiple services
        if (isset($this->types[$type])) {
            if ($this->types[$type] === $id) {
                return;
            }

            // keep an array of all services matching this type
            $this->addServiceToAmbiguousType($id, $type);

            unset($this->types[$type]);

            return;
        }

        $this->types[$type] = $id;
    }

    /**
     * Registers a definition for the type if possible or throws an exception.
     *
     * @param \ReflectionClass $typeHint
     *
     * @return Reference A reference to the registered definition
     *
     * @throws RuntimeException
     */
    private function createAutowiredDefinition(\ReflectionClass $typeHint)
    {
        if (isset($this->ambiguousServiceTypes[$typeHint->name])) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            $matchingServices = implode(', ', $this->ambiguousServiceTypes[$typeHint->name]);

            throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s". Multiple services exist for this %s (%s).', $typeHint->name, $this->currentId, $classOrInterface, $matchingServices), 1);
        }

        if (!$typeHint->isInstantiable()) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s". No services were found matching this %s and it cannot be auto-registered.', $typeHint->name, $this->currentId, $classOrInterface));
        }

        $currentId = $this->currentId;
        $this->currentId = $argumentId = sprintf('autowired.%s', $typeHint->name);

        $argumentDefinition = $this->container->register($argumentId, $typeHint->name);
        $argumentDefinition->setPublic(false);
        $argumentDefinition->setAutowired(true);

        $this->populateAvailableType($argumentId, $argumentDefinition);

        try {
            $this->processValue($argumentDefinition, true);
            $this->currentId = $currentId;
        } catch (RuntimeException $e) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            $message = sprintf('Unable to autowire argument of type "%s" for the service "%s". No services were found matching this %s and it cannot be auto-registered.', $typeHint->name, $this->currentId, $classOrInterface);
            throw new RuntimeException($message, 0, $e);
        }

        return new Reference($argumentId);
    }

    /**
     * Retrieves the reflection class associated with the given service.
     *
     * @param string|null $id
     * @param Definition  $definition
     *
     * @return \ReflectionClass|false
     */
    private function getReflectionClass($id, Definition $definition)
    {
        if (null !== $id && isset($this->reflectionClasses[$id])) {
            return $this->reflectionClasses[$id];
        }

        // Cannot use reflection if the class isn't set
        if (!$class = $definition->getClass()) {
            return false;
        }

        $class = $this->container->getParameterBag()->resolveValue($class);

        try {
            $reflector = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            $reflector = false;
        }

        if (null !== $id) {
            $this->reflectionClasses[$id] = $reflector;
        }

        return $reflector;
    }

    private function addServiceToAmbiguousType($id, $type)
    {
        // keep an array of all services matching this type
        if (!isset($this->ambiguousServiceTypes[$type])) {
            $this->ambiguousServiceTypes[$type] = array(
                $this->types[$type],
            );
        }
        $this->ambiguousServiceTypes[$type][] = $id;
    }

    private static function getResourceMetadataForMethod(\ReflectionMethod $method)
    {
        $methodArgumentsMetadata = array();
        foreach ($method->getParameters() as $parameter) {
            try {
                $class = $parameter->getClass();
            } catch (\ReflectionException $e) {
                // type-hint is against a non-existent class
                $class = false;
            }

            $isVariadic = method_exists($parameter, 'isVariadic') && $parameter->isVariadic();
            $methodArgumentsMetadata[] = array(
                'class' => $class,
                'isOptional' => $parameter->isOptional(),
                'defaultValue' => ($parameter->isOptional() && !$isVariadic) ? $parameter->getDefaultValue() : null,
            );
        }

        return $methodArgumentsMetadata;
    }
}
