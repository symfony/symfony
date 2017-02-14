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
class AutowirePass implements CompilerPassInterface
{
    private $container;
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
            $this->container = $container;
            foreach ($container->getDefinitions() as $id => $definition) {
                if ($definition->isAutowired()) {
                    $this->completeDefinition($id, $definition);
                }
            }
        } finally {
            spl_autoload_unregister($throwingAutoloader);

            // Free memory and remove circular reference to container
            $this->container = null;
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

        foreach (self::getSetters($reflectionClass) as $reflectionMethod) {
            $metadata[$reflectionMethod->name] = self::getResourceMetadataForMethod($reflectionMethod);
        }

        return new AutowireServiceResource($reflectionClass->name, $reflectionClass->getFileName(), $metadata);
    }

    /**
     * Wires the given definition.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @throws RuntimeException
     */
    private function completeDefinition($id, Definition $definition)
    {
        if (!$reflectionClass = $this->getReflectionClass($id, $definition)) {
            return;
        }

        if ($this->container->isTrackingResources()) {
            $this->container->addResource(static::createResourceForClass($reflectionClass));
        }

        if (!$constructor = $reflectionClass->getConstructor()) {
            return;
        }

        $arguments = $definition->getArguments();
        foreach ($constructor->getParameters() as $index => $parameter) {
            if (array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }

            try {
                if (!$typeHint = $parameter->getClass()) {
                    // no default value? Then fail
                    if (!$parameter->isOptional()) {
                        throw new RuntimeException(sprintf('Unable to autowire argument index %d ($%s) for the service "%s". If this is an object, give it a type-hint. Otherwise, specify this argument\'s value explicitly.', $index, $parameter->name, $id));
                    }

                    if (!array_key_exists($index, $arguments)) {
                        // specifically pass the default value
                        $arguments[$index] = $parameter->getDefaultValue();
                    }

                    continue;
                }

                if (null === $this->types) {
                    $this->populateAvailableTypes();
                }

                if (isset($this->types[$typeHint->name])) {
                    $value = new Reference($this->types[$typeHint->name]);
                } else {
                    try {
                        $value = $this->createAutowiredDefinition($typeHint, $id);
                    } catch (RuntimeException $e) {
                        if ($parameter->allowsNull()) {
                            $value = null;
                        } elseif ($parameter->isDefaultValueAvailable()) {
                            $value = $parameter->getDefaultValue();
                        } else {
                            throw $e;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // Typehint against a non-existing class

                if (!$parameter->isDefaultValueAvailable()) {
                    throw new RuntimeException(sprintf('Cannot autowire argument %s for %s because the type-hinted class does not exist (%s).', $index + 1, $definition->getClass(), $e->getMessage()), 0, $e);
                }

                $value = $parameter->getDefaultValue();
            }

            $arguments[$index] = $value;
        }

        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments);
        $definition->setArguments($arguments);
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
            $this->ambiguousServiceTypes[$type][] = $id;

            return;
        }

        // check to make sure the type doesn't match multiple services
        if (!isset($this->types[$type]) || $this->types[$type] === $id) {
            $this->types[$type] = $id;

            return;
        }

        // keep an array of all services matching this type
        if (!isset($this->ambiguousServiceTypes[$type])) {
            $this->ambiguousServiceTypes[$type] = array($this->types[$type]);
            unset($this->types[$type]);
        }
        $this->ambiguousServiceTypes[$type][] = $id;
    }

    /**
     * Registers a definition for the type if possible or throws an exception.
     *
     * @param \ReflectionClass $typeHint
     * @param string           $id
     *
     * @return Reference A reference to the registered definition
     *
     * @throws RuntimeException
     */
    private function createAutowiredDefinition(\ReflectionClass $typeHint, $id)
    {
        if (isset($this->ambiguousServiceTypes[$typeHint->name])) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            $matchingServices = implode(', ', $this->ambiguousServiceTypes[$typeHint->name]);

            throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s". Multiple services exist for this %s (%s).', $typeHint->name, $id, $classOrInterface, $matchingServices));
        }

        if (!$typeHint->isInstantiable()) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s". No services were found matching this %s and it cannot be auto-registered.', $typeHint->name, $id, $classOrInterface));
        }

        $argumentId = sprintf('autowired.%s', $typeHint->name);

        $argumentDefinition = $this->container->register($argumentId, $typeHint->name);
        $argumentDefinition->setPublic(false);

        $this->populateAvailableType($argumentId, $argumentDefinition);

        try {
            $this->completeDefinition($argumentId, $argumentDefinition);
        } catch (RuntimeException $e) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            $message = sprintf('Unable to autowire argument of type "%s" for the service "%s". No services were found matching this %s and it cannot be auto-registered.', $typeHint->name, $id, $classOrInterface);
            throw new RuntimeException($message, 0, $e);
        }

        return new Reference($argumentId);
    }

    /**
     * Retrieves the reflection class associated with the given service.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return \ReflectionClass|false
     */
    private function getReflectionClass($id, Definition $definition)
    {
        if (isset($this->reflectionClasses[$id])) {
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

        return $this->reflectionClasses[$id] = $reflector;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     *
     * @return \ReflectionMethod[]
     */
    private static function getSetters(\ReflectionClass $reflectionClass)
    {
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (!$reflectionMethod->isStatic() && 1 === $reflectionMethod->getNumberOfParameters() && 0 === strpos($reflectionMethod->name, 'set')) {
                yield $reflectionMethod;
            }
        }
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
