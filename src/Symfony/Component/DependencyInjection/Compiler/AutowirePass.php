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
class AutowirePass extends AbstractRecursivePass
{
    /**
     * @internal
     */
    const MODE_REQUIRED = 1;

    /**
     * @internal
     */
    const MODE_OPTIONAL = 2;

    private $definedTypes = array();
    private $types;
    private $ambiguousServiceTypes = array();
    private $usedTypes = array();

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        try {
            parent::process($container);

            foreach ($this->usedTypes as $type => $id) {
                if (!isset($this->usedTypes[$type]) || !isset($this->ambiguousServiceTypes[$type])) {
                    continue;
                }

                if ($container->has($type) && !$container->findDefinition($type)->isAbstract()) {
                    continue;
                }

                $classOrInterface = class_exists($type) ? 'class' : 'interface';
                $matchingServices = implode(', ', $this->ambiguousServiceTypes[$type]);

                throw new RuntimeException(sprintf('Unable to autowire argument of type "%s" for the service "%s". Multiple services exist for this %s (%s).', $type, $id, $classOrInterface, $matchingServices));
            }
        } finally {
            // Free memory
            $this->definedTypes = array();
            $this->types = null;
            $this->ambiguousServiceTypes = array();
            $this->usedTypes = array();
        }
    }

    /**
     * Creates a resource to help know if this service has changed.
     *
     * @param \ReflectionClass $reflectionClass
     *
     * @return AutowireServiceResource
     *
     * @deprecated since version 3.3, to be removed in 4.0. Use ContainerBuilder::getReflectionClass() instead.
     */
    public static function createResourceForClass(\ReflectionClass $reflectionClass)
    {
        @trigger_error('The '.__METHOD__.'() method is deprecated since version 3.3 and will be removed in 4.0. Use ContainerBuilder::getReflectionClass() instead.', E_USER_DEPRECATED);

        $metadata = array();

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
        if (!$value instanceof Definition || !$value->isAutowired()) {
            return parent::processValue($value, $isRoot);
        }

        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass())) {
            return parent::processValue($value, $isRoot);
        }

        $autowiredMethods = $this->getMethodsToAutowire($reflectionClass);
        $methodCalls = $value->getMethodCalls();

        if ($constructor = $reflectionClass->getConstructor()) {
            array_unshift($methodCalls, array($constructor->name, $value->getArguments()));
        } elseif ($value->getArguments()) {
            throw new RuntimeException(sprintf('Cannot autowire service "%s": class %s has no constructor but arguments are defined.', $this->currentId, $reflectionClass->name, $method));
        }

        $methodCalls = $this->autowireCalls($reflectionClass, $methodCalls, $autowiredMethods);
        $overriddenGetters = $this->autowireOverridenGetters($value->getOverriddenGetters(), $autowiredMethods);

        if ($constructor) {
            list(, $arguments) = array_shift($methodCalls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($methodCalls);
        }

        if ($overriddenGetters !== $value->getOverriddenGetters()) {
            $value->setOverriddenGetters($overriddenGetters);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Gets the list of methods to autowire.
     *
     * @param \ReflectionClass $reflectionClass
     *
     * @return \ReflectionMethod[]
     */
    private function getMethodsToAutowire(\ReflectionClass $reflectionClass)
    {
        $found = array();
        $methodsToAutowire = array();

        if ($reflectionMethod = $reflectionClass->getConstructor()) {
            $methodsToAutowire[strtolower($reflectionMethod->name)] = $reflectionMethod;
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }
            if ($reflectionMethod->isAbstract() && !$reflectionMethod->getNumberOfParameters()) {
                $methodsToAutowire[strtolower($reflectionMethod->name)] = $reflectionMethod;
                continue;
            }
            $r = $reflectionMethod;

            while (true) {
                if (false !== $doc = $r->getDocComment()) {
                    if (false !== stripos($doc, '@required') && preg_match('#(?:^/\*\*|\n\s*+\*)\s*+@required(?:\s|\*/$)#i', $doc)) {
                        $methodsToAutowire[strtolower($reflectionMethod->name)] = $reflectionMethod;
                        break;
                    }
                    if (false === stripos($doc, '@inheritdoc') || !preg_match('#(?:^/\*\*|\n\s*+\*)\s*+(?:\{@inheritdoc\}|@inheritdoc)(?:\s|\*/$)#i', $doc)) {
                        break;
                    }
                }
                try {
                    $r = $r->getPrototype();
                } catch (\ReflectionException $e) {
                    break; // method has no prototype
                }
            }
        }

        return $methodsToAutowire;
    }

    /**
     * @param \ReflectionClass    $reflectionClass
     * @param array               $methodCalls
     * @param \ReflectionMethod[] $autowiredMethods
     *
     * @return array
     */
    private function autowireCalls(\ReflectionClass $reflectionClass, array $methodCalls, array $autowiredMethods)
    {
        foreach ($methodCalls as $i => $call) {
            list($method, $arguments) = $call;

            if (isset($autowiredMethods[$lcMethod = strtolower($method)]) && $autowiredMethods[$lcMethod]->isPublic()) {
                $reflectionMethod = $autowiredMethods[$lcMethod];
                unset($autowiredMethods[$lcMethod]);
            } else {
                if (!$reflectionClass->hasMethod($method)) {
                    throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s::%s() does not exist.', $this->currentId, $reflectionClass->name, $method));
                }
                $reflectionMethod = $reflectionClass->getMethod($method);
                if (!$reflectionMethod->isPublic()) {
                    throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s::%s() must be public.', $this->currentId, $reflectionClass->name, $method));
                }
            }

            $arguments = $this->autowireMethod($reflectionMethod, $arguments, self::MODE_REQUIRED);

            if ($arguments !== $call[1]) {
                $methodCalls[$i][1] = $arguments;
            }
        }

        foreach ($autowiredMethods as $lcMethod => $reflectionMethod) {
            if ($reflectionMethod->isPublic() && $arguments = $this->autowireMethod($reflectionMethod, array(), self::MODE_OPTIONAL)) {
                $methodCalls[] = array($reflectionMethod->name, $arguments);
            }
        }

        return $methodCalls;
    }

    /**
     * Autowires the constructor or a method.
     *
     * @param \ReflectionMethod $reflectionMethod
     * @param array             $arguments
     * @param int               $mode
     *
     * @return array The autowired arguments
     *
     * @throws RuntimeException
     */
    private function autowireMethod(\ReflectionMethod $reflectionMethod, array $arguments, $mode)
    {
        $didAutowire = false; // Whether any arguments have been autowired or not
        foreach ($reflectionMethod->getParameters() as $index => $parameter) {
            if (array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }
            if (self::MODE_OPTIONAL === $mode && $parameter->isOptional() && !array_key_exists($index, $arguments)) {
                break;
            }
            if (method_exists($parameter, 'isVariadic') && $parameter->isVariadic()) {
                continue;
            }

            if (method_exists($parameter, 'getType')) {
                if ($typeName = $parameter->getType()) {
                    $typeName = $typeName->isBuiltin() ? null : ($typeName instanceof \ReflectionNamedType ? $typeName->getName() : $typeName->__toString());
                }
            } elseif (preg_match('/^(?:[^ ]++ ){4}([a-zA-Z_\x7F-\xFF][^ ]++)/', $parameter, $typeName)) {
                $typeName = 'callable' === $typeName[1] || 'array' === $typeName[1] ? null : $typeName[1];
            }

            if (!$typeName) {
                // no default value? Then fail
                if (!$parameter->isOptional()) {
                    if (self::MODE_REQUIRED === $mode) {
                        throw new RuntimeException(sprintf('Cannot autowire service "%s": argument $%s of method %s::%s() must have a type-hint or be given a value explicitly.', $this->currentId, $parameter->name, $reflectionMethod->class, $reflectionMethod->name));
                    }

                    return array();
                }

                if (!array_key_exists($index, $arguments)) {
                    // specifically pass the default value
                    $arguments[$index] = $parameter->getDefaultValue();
                }

                continue;
            }

            if ($this->container->has($typeName) && !$this->container->findDefinition($typeName)->isAbstract()) {
                $arguments[$index] = new Reference($typeName);
                $didAutowire = true;

                continue;
            }

            if (null === $this->types) {
                $this->populateAvailableTypes();
            }

            if (isset($this->types[$typeName])) {
                $value = new Reference($this->types[$typeName]);
                $didAutowire = true;
                $this->usedTypes[$typeName] = $this->currentId;
            } elseif ($typeHint = $this->container->getReflectionClass($typeName, true)) {
                try {
                    $value = $this->createAutowiredDefinition($typeHint);
                    $didAutowire = true;
                    $this->usedTypes[$typeName] = $this->currentId;
                } catch (RuntimeException $e) {
                    if ($parameter->allowsNull()) {
                        $value = null;
                    } elseif ($parameter->isDefaultValueAvailable()) {
                        $value = $parameter->getDefaultValue();
                    } else {
                        // The exception code is set to 1 if the exception must be thrown even if it's an optional setter
                        if (1 === $e->getCode() || self::MODE_REQUIRED === $mode) {
                            throw $e;
                        }

                        return array();
                    }
                }
            } else {
                // Typehint against a non-existing class

                if (!$parameter->isDefaultValueAvailable()) {
                    if (self::MODE_REQUIRED === $mode) {
                        throw new RuntimeException(sprintf('Cannot autowire argument $%s of method %s::%s() for service "%s": Class %s does not exist.', $parameter->name, $reflectionMethod->class, $reflectionMethod->name, $this->currentId, $typeName));
                    }

                    return array();
                }

                $value = $parameter->getDefaultValue();
            }

            $arguments[$index] = $value;
        }

        if (self::MODE_REQUIRED !== $mode && !$didAutowire) {
            return array();
        }

        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments);

        return $arguments;
    }

    /**
     * Autowires getters.
     *
     * @param array $overridenGetters
     * @param array $autowiredMethods
     *
     * @return array
     */
    private function autowireOverridenGetters(array $overridenGetters, array $autowiredMethods)
    {
        foreach ($autowiredMethods as $lcMethod => $reflectionMethod) {
            if (isset($overridenGetters[$lcMethod])
                || !method_exists($reflectionMethod, 'getReturnType')
                || 0 !== $reflectionMethod->getNumberOfParameters()
                || $reflectionMethod->isFinal()
                || $reflectionMethod->returnsReference()
                || !$returnType = $reflectionMethod->getReturnType()
            ) {
                continue;
            }
            $typeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : $returnType->__toString();

            if ($this->container->has($typeName) && !$this->container->findDefinition($typeName)->isAbstract()) {
                $overridenGetters[$lcMethod] = new Reference($typeName);
                continue;
            }

            if (null === $this->types) {
                $this->populateAvailableTypes();
            }

            if (isset($this->types[$typeName])) {
                $value = new Reference($this->types[$typeName]);
            } elseif ($returnType = $this->container->getReflectionClass($typeName, true)) {
                try {
                    $value = $this->createAutowiredDefinition($returnType);
                } catch (RuntimeException $e) {
                    if (1 === $e->getCode()) {
                        throw $e;
                    }

                    continue;
                }
            } else {
                continue;
            }

            $overridenGetters[$lcMethod] = $value;
            $this->usedTypes[$typeName] = $this->currentId;
        }

        return $overridenGetters;
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

        foreach ($definition->getAutowiringTypes(false) as $type) {
            $this->definedTypes[$type] = true;
            $this->types[$type] = $id;
            unset($this->ambiguousServiceTypes[$type]);
        }

        if (!$reflectionClass = $this->container->getReflectionClass($definition->getClass(), true)) {
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
            $this->processValue($argumentDefinition);
            $this->currentId = $currentId;
        } catch (RuntimeException $e) {
            $classOrInterface = $typeHint->isInterface() ? 'interface' : 'class';
            $message = sprintf('Unable to autowire argument of type "%s" for the service "%s". No services were found matching this %s and it cannot be auto-registered.', $typeHint->name, $this->currentId, $classOrInterface);
            throw new RuntimeException($message, 0, $e);
        }

        return new Reference($argumentId);
    }

    /**
     * @deprecated since version 3.3, to be removed in 4.0.
     */
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
