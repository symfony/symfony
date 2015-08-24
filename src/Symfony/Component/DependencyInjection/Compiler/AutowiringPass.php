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
class AutowiringPass implements CompilerPassInterface
{
    private $container;
    private $definitions;
    private $reflectionClassesToId = array();
    private $definedTypes = array();
    private $typesToId;
    private $notGuessableTypesToId = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->definitions = $container->getDefinitions();
        foreach ($this->definitions as $id => $definition) {
            $this->completeDefinition($id, $definition, $container);
        }
    }

    /**
     * Wires the given definition.
     *
     * @param string $id
     * @param Definition $definition
     *
     * @throws RuntimeException
     */
    private function completeDefinition($id, Definition $definition)
    {
        if (!($reflectionClass = $this->getReflectionClass($id, $definition))) {
            return;
        }

        if (!($constructor = $reflectionClass->getConstructor())) {
            return;
        }

        $arguments = $definition->getArguments();
        foreach ($constructor->getParameters() as $index => $parameter) {
            if (!($typeHint = $parameter->getClass()) || $parameter->isOptional()) {
                continue;
            }

            $argumentExist = array_key_exists($index, $arguments);
            if ($argumentExist && '' !== $arguments[$index]) {
                continue;
            }

            if (null === $this->typesToId) {
                $this->populateAvailableTypes();
            }

            if (isset($this->typesToId[$typeHint->name])) {
                $reference = new Reference($this->typesToId[$typeHint->name]);
            } else {
                $reference = $this->createAutowiredDefinition($typeHint);
            }

            if ($argumentExist) {
                $definition->replaceArgument($index, $reference);
            } else {
                $definition->addArgument($reference);
            }
        }
    }

    /**
     * Populates the list of available types.
     */
    private function populateAvailableTypes()
    {
        $this->typesToId = array();

        foreach ($this->definitions as $id => $definition) {
            $this->populateAvailableType($id, $definition);
        }
    }

    /**
     * Populates the of available types for a given definition.
     *
     * @param string     $id
     * @param Definition $definition
     */
    private function populateAvailableType($id, Definition $definition) {
        if (!($class = $definition->getClass())) {
            return;
        }

        foreach ($definition->getTypes() as $type) {
            $this->definedTypes[$type] = true;
            $this->typesToId[$type] = $id;
        }

        if ($reflectionClass = $this->getReflectionClass($id, $definition)) {
            $this->extractInterfaces($id, $reflectionClass);
            $this->extractAncestors($id, $reflectionClass);
        }
    }

    /**
     * Extracts the list of all interfaces implemented by a class.
     *
     * @param string           $id
     * @param \ReflectionClass $reflectionClass
     */
    private function extractInterfaces($id, \ReflectionClass $reflectionClass)
    {
        foreach ($reflectionClass->getInterfaces() as $interfaceName => $reflectionInterface) {
            $this->set($interfaceName, $id);

            $this->extractInterfaces($id, $reflectionInterface);
        }
    }

    /**
     * Extracts all inherited types of a class.
     *
     * @param string           $id
     * @param \ReflectionClass $reflectionClass
     */
    private function extractAncestors($id, \ReflectionClass $reflectionClass)
    {
        $this->set($reflectionClass->name, $id);

        if ($reflectionParentClass = $reflectionClass->getParentClass()) {
            $this->extractAncestors($id, $reflectionParentClass);
        }
    }

    /**
     * Associates if applicable a type and a service id or a class.
     *
     * @param string $type
     * @param string $value A service id or a class name depending of the value of $class
     */
    private function set($type, $value)
    {
        if (isset($this->definedTypes[$type]) || isset($this->notGuessableTypesToId[$type])) {
            return;
        }

        if (isset($this->typesToId[$type])) {
            if ($this->typesToId[$type] === $value) {
                return;
            }

            unset($this->typesToId[$type]);

            $this->notGuessableTypesToId[$type] = true;
            return;
        }

        $this->typesToId[$type] = $value;
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
        if (!$typeHint->isInstantiable()) {
            throw new RuntimeException(sprintf('Unable to autowire type "%s".', $typeHint->name));
        }

        $argumentId = sprintf('autowired.%s', $typeHint->name);

        $argumentDefinition = $this->container->register($argumentId, $typeHint->name);
        $argumentDefinition->setPublic(false);

        $this->definitions = $this->container->getDefinitions();
        $this->populateAvailableType($argumentId, $argumentDefinition);
        $this->completeDefinition($argumentId, $argumentDefinition);

        return new Reference($argumentId);
    }

    /**
     * Retrieves the reflection class associated with the given service.
     *
     * @param string $id
     * @param Definition $definition
     *
     * @return \ReflectionClass|null
     */
    private function getReflectionClass($id, Definition $definition)
    {
        if (isset($this->reflectionClassesToId[$id])) {
            return $this->reflectionClassesToId[$id];
        }

        if (!$class = $definition->getClass()) {
            return;
        }

        try {
            return $this->reflectionClassesToId[$id] = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            // Skip invalid classes definitions to keep BC
        }
    }
}
