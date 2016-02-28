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
    private $notGuessableTypes = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAutowired()) {
                $this->completeDefinition($id, $definition);
            }
        }

        // Free memory and remove circular reference to container
        $this->container = null;
        $this->reflectionClasses = array();
        $this->definedTypes = array();
        $this->types = null;
        $this->notGuessableTypes = array();
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

        $this->container->addClassResource($reflectionClass);

        if (!$constructor = $reflectionClass->getConstructor()) {
            return;
        }

        $arguments = $definition->getArguments();
        foreach ($constructor->getParameters() as $index => $parameter) {
            $argumentExists = array_key_exists($index, $arguments);
            if ($argumentExists && '' !== $arguments[$index]) {
                continue;
            }

            try {
                if (!$typeHint = $parameter->getClass()) {
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
            } catch (\ReflectionException $reflectionException) {
                // Typehint against a non-existing class

                if (!$parameter->isDefaultValueAvailable()) {
                    throw new RuntimeException(sprintf('Cannot autowire argument %s for %s because the type-hinted class does not exist (%s).', $index + 1, $definition->getClass(), $reflectionException->getMessage()), 0, $reflectionException);
                }

                $value = $parameter->getDefaultValue();
            }

            if ($argumentExists) {
                $definition->replaceArgument($index, $value);
            } else {
                $definition->addArgument($value);
            }
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
        if (isset($this->definedTypes[$type]) || isset($this->notGuessableTypes[$type])) {
            return;
        }

        if (isset($this->types[$type])) {
            if ($this->types[$type] === $id) {
                return;
            }

            unset($this->types[$type]);
            $this->notGuessableTypes[$type] = true;

            return;
        }

        $this->types[$type] = $id;
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
        if (isset($this->notGuessableTypes[$typeHint->name]) || !$typeHint->isInstantiable()) {
            throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s".', $typeHint->name, $id));
        }

        $argumentId = sprintf('autowired.%s', $typeHint->name);

        $argumentDefinition = $this->container->register($argumentId, $typeHint->name);
        $argumentDefinition->setPublic(false);

        $this->populateAvailableType($argumentId, $argumentDefinition);
        $this->completeDefinition($argumentId, $argumentDefinition);

        return new Reference($argumentId);
    }

    /**
     * Retrieves the reflection class associated with the given service.
     *
     * @param string     $id
     * @param Definition $definition
     *
     * @return \ReflectionClass|null
     */
    private function getReflectionClass($id, Definition $definition)
    {
        if (isset($this->reflectionClasses[$id])) {
            return $this->reflectionClasses[$id];
        }

        // Cannot use reflection if the class isn't set
        if (!$class = $definition->getClass()) {
            return;
        }

        $class = $this->container->getParameterBag()->resolveValue($class);

        try {
            return $this->reflectionClasses[$id] = new \ReflectionClass($class);
        } catch (\ReflectionException $reflectionException) {
            // return null
        }
    }
}
