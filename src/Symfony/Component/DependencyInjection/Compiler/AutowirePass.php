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
use Symfony\Component\DependencyInjection\LazyProxy\InheritanceProxyHelper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Inspects existing service definitions and wires the autowired ones using the type hints of their classes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowirePass extends AbstractRecursivePass
{
    private $definedTypes = array();
    private $types;
    private $ambiguousServiceTypes = array();
    private $usedTypes = array();
    private $currentDefinition;

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

                $this->container = $container;
                $classOrInterface = class_exists($type, false) ? 'class' : 'interface';

                throw new RuntimeException(sprintf('Cannot autowire service "%s": multiple candidate services exist for %s "%s".%s', $id, $classOrInterface, $type, $this->createTypeAlternatives($type)));
            }
        } finally {
            $this->container = null;
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
        if ($value instanceof TypedReference && $this->currentDefinition->isAutowired() && !$this->container->has((string) $value)) {
            if ($ref = $this->getAutowiredReference($value->getType(), $value->canBeAutoregistered())) {
                $value = new TypedReference((string) $ref, $value->getType(), $value->getInvalidBehavior(), $value->canBeAutoregistered());
            } else {
                $this->container->log($this, $this->createTypeNotFoundMessage($value->getType(), 'typed reference'));
            }
        }
        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }

        $parentDefinition = $this->currentDefinition;
        $this->currentDefinition = $value;

        try {
            if (!$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
                return parent::processValue($value, $isRoot);
            }
            if (!$reflectionClass = $this->container->getReflectionClass($value->getClass())) {
                $this->container->log($this, sprintf('Skipping service "%s": Class or interface "%s" does not exist.', $this->currentId, $value->getClass()));

                return parent::processValue($value, $isRoot);
            }

            $autowiredMethods = $this->getMethodsToAutowire($reflectionClass);
            $methodCalls = $value->getMethodCalls();

            if ($constructor = $reflectionClass->getConstructor()) {
                array_unshift($methodCalls, array($constructor->name, $value->getArguments()));
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
        } finally {
            $this->currentDefinition = $parentDefinition;
        }
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
                    $class = $reflectionClass->name;
                    throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s() does not exist.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
                }
                $reflectionMethod = $reflectionClass->getMethod($method);
                if (!$reflectionMethod->isPublic()) {
                    $class = $reflectionClass->name;
                    throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s() must be public.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
                }
            }

            $arguments = $this->autowireMethod($reflectionMethod, $arguments);

            if ($arguments !== $call[1]) {
                $methodCalls[$i][1] = $arguments;
            }
        }

        foreach ($autowiredMethods as $lcMethod => $reflectionMethod) {
            if (!$reflectionMethod->getNumberOfParameters()) {
                continue; // skip getters
            }
            $method = $reflectionMethod->name;

            if (!$reflectionMethod->isPublic()) {
                $class = $reflectionClass->name;
                throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s() must be public.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
            }
            $methodCalls[] = array($method, $this->autowireMethod($reflectionMethod, array()));
        }

        return $methodCalls;
    }

    /**
     * Autowires the constructor or a method.
     *
     * @param \ReflectionMethod $reflectionMethod
     * @param array             $arguments
     *
     * @return array The autowired arguments
     *
     * @throws RuntimeException
     */
    private function autowireMethod(\ReflectionMethod $reflectionMethod, array $arguments)
    {
        $isConstructor = $reflectionMethod->isConstructor();
        $class = $reflectionMethod->class;
        $method = $reflectionMethod->name;

        if (!$isConstructor && !$arguments && !$reflectionMethod->getNumberOfRequiredParameters()) {
            throw new RuntimeException(sprintf('Cannot autowire service "%s": method %s() has only optional arguments, thus must be wired explicitly.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
        }

        foreach ($reflectionMethod->getParameters() as $index => $parameter) {
            if (array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }
            if (!$isConstructor && $parameter->isOptional() && !array_key_exists($index, $arguments)) {
                break;
            }
            if (method_exists($parameter, 'isVariadic') && $parameter->isVariadic()) {
                continue;
            }

            $type = InheritanceProxyHelper::getTypeHint($reflectionMethod, $parameter, true);

            if (!$type) {
                // no default value? Then fail
                if (!$parameter->isOptional()) {
                    throw new RuntimeException(sprintf('Cannot autowire service "%s": argument $%s of method %s() must have a type-hint or be given a value explicitly.', $this->currentId, $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));
                }

                if (!array_key_exists($index, $arguments)) {
                    // specifically pass the default value
                    $arguments[$index] = $parameter->getDefaultValue();
                }

                continue;
            }

            if ($value = $this->getAutowiredReference($type)) {
                $this->usedTypes[$type] = $this->currentId;
            } else {
                $failureMessage = $this->createTypeNotFoundMessage($type, sprintf('argument $%s of method %s()', $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));

                if ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $value = null;
                } else {
                    throw new RuntimeException($failureMessage);
                }
                $this->container->log($this, $failureMessage);
            }

            $arguments[$index] = $value;
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
            if (isset($overridenGetters[$lcMethod]) || $reflectionMethod->getNumberOfParameters() || $reflectionMethod->isConstructor()) {
                continue;
            }
            $class = $reflectionMethod->class;
            $method = $reflectionMethod->name;

            if (!$type = InheritanceProxyHelper::getTypeHint($reflectionMethod, null, true)) {
                $type = InheritanceProxyHelper::getTypeHint($reflectionMethod);

                throw new RuntimeException(sprintf('Cannot autowire service "%s": getter %s() must%s have its return value be configured explicitly.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method, $type ? '' : ' have a return-type hint or'));
            }

            if (!$typeRef = $this->getAutowiredReference($type)) {
                $this->container->log($this, $this->createTypeNotFoundMessage($type, sprintf('return value of method %s()', $class !== $this->currentId ? $class.'::'.$method : $method)));
                continue;
            }

            $overridenGetters[$lcMethod] = $typeRef;
            $this->usedTypes[$type] = $this->currentId;
        }

        return $overridenGetters;
    }

    /**
     * @return Reference|null A reference to the service matching the given type, if any
     */
    private function getAutowiredReference($type, $autoRegister = true)
    {
        if ($this->container->has($type) && !$this->container->findDefinition($type)->isAbstract()) {
            return new Reference($type);
        }

        if (Definition::AUTOWIRE_BY_ID === $this->currentDefinition->getAutowired()) {
            return;
        }

        if (null === $this->types) {
            $this->populateAvailableTypes();
        }

        if (isset($this->types[$type])) {
            $this->container->log($this, sprintf('Service "%s" matches type "%s" and has been autowired into service "%s".', $this->types[$type], $type, $this->currentId));

            return new Reference($this->types[$type]);
        }

        if ($autoRegister && $class = $this->container->getReflectionClass($type, true)) {
            return $this->createAutowiredDefinition($class);
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
     * @return Reference|null A reference to the registered definition
     *
     * @throws RuntimeException
     */
    private function createAutowiredDefinition(\ReflectionClass $typeHint)
    {
        if (isset($this->ambiguousServiceTypes[$type = $typeHint->name])) {
            $classOrInterface = class_exists($type) ? 'class' : 'interface';

            throw new RuntimeException(sprintf('Cannot autowire service "%s": multiple candidate services exist for %s "%s".%s', $this->currentId, $classOrInterface, $type, $this->createTypeAlternatives($type)));
        }

        if (!$typeHint->isInstantiable()) {
            $this->container->log($this, sprintf('Type "%s" is not instantiable thus cannot be auto-registered for service "%s".', $type, $this->currentId));

            return;
        }

        $ambiguousServiceTypes = $this->ambiguousServiceTypes;
        $currentDefinition = $this->currentDefinition;
        $definitions = $this->container->getDefinitions();
        $currentId = $this->currentId;
        $this->currentId = $argumentId = sprintf('autowired.%s', $type);
        $this->currentDefinition = $argumentDefinition = new Definition($type);
        $argumentDefinition->setPublic(false);
        $argumentDefinition->setAutowired(true);

        $this->populateAvailableType($argumentId, $argumentDefinition);

        try {
            $this->processValue($argumentDefinition, true);
            $this->container->setDefinition($argumentId, $argumentDefinition);
        } catch (RuntimeException $e) {
            // revert any changes done to our internal state
            unset($this->types[$type]);
            $this->ambiguousServiceTypes = $ambiguousServiceTypes;
            $this->container->setDefinitions($definitions);
            $this->container->log($this, $e->getMessage());

            return;
        } finally {
            $this->currentId = $currentId;
            $this->currentDefinition = $currentDefinition;
        }

        $this->container->log($this, sprintf('Type "%s" has been auto-registered for service "%s".', $type, $this->currentId));

        return new Reference($argumentId);
    }

    private function createTypeNotFoundMessage($type, $label)
    {
        $autowireById = Definition::AUTOWIRE_BY_ID === $this->currentDefinition->getAutowired();
        if (!$classOrInterface = class_exists($type, $autowireById) ? 'class' : (interface_exists($type, false) ? 'interface' : null)) {
            return sprintf('Cannot autowire service "%s": %s has type "%s" but this class does not exist.', $this->currentId, $label, $type);
        }
        if (null === $this->types) {
            $this->populateAvailableTypes();
        }
        if ($autowireById) {
            $message = sprintf('%s references %s "%s" but no such service exists.%s', $label, $classOrInterface, $type, $this->createTypeAlternatives($type));
        } else {
            $message = sprintf('no services were found matching the "%s" %s and it cannot be auto-registered for %s.', $type, $classOrInterface, $label);
        }

        return sprintf('Cannot autowire service "%s": %s', $this->currentId, $message);
    }

    private function createTypeAlternatives($type)
    {
        $message = ' This type-hint could be aliased to ';

        if (isset($this->ambiguousServiceTypes[$type])) {
            $message .= sprintf('one of these existing services: "%s"', implode('", "', $this->ambiguousServiceTypes[$type]));
        } elseif (isset($this->types[$type])) {
            $message .= sprintf('the existing "%s" service', $this->types[$type]);
        } else {
            return;
        }
        $aliases = array();

        foreach (class_parents($type) + class_implements($type) as $parent) {
            if ($this->container->has($parent)) {
                $aliases[] = $parent;
            }
        }

        if (1 < count($aliases)) {
            $message .= sprintf('; or be updated to one of the following: "%s"', implode('", "', $aliases));
        } elseif ($aliases) {
            $message .= sprintf('; or be updated to "%s"', $aliases[0]);
        }

        return $message.'.';
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
