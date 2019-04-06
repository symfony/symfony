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

use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\LazyProxy\ProxyHelper;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Inspects existing service definitions and wires the autowired ones using the type hints of their classes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowirePass extends AbstractRecursivePass
{
    private $types;
    private $ambiguousServiceTypes;
    private $lastFailure;
    private $throwOnAutowiringException;
    private $decoratedClass;
    private $decoratedId;
    private $methodCalls;
    private $getPreviousValue;
    private $decoratedMethodIndex;
    private $decoratedMethodArgumentIndex;

    public function __construct(bool $throwOnAutowireException = true)
    {
        $this->throwOnAutowiringException = $throwOnAutowireException;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        try {
            parent::process($container);
        } finally {
            $this->types = null;
            $this->ambiguousServiceTypes = null;
            $this->decoratedClass = null;
            $this->decoratedId = null;
            $this->methodCalls = null;
            $this->getPreviousValue = null;
            $this->decoratedMethodIndex = null;
            $this->decoratedMethodArgumentIndex = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        try {
            return $this->doProcessValue($value, $isRoot);
        } catch (AutowiringFailedException $e) {
            if ($this->throwOnAutowiringException) {
                throw $e;
            }

            $this->container->getDefinition($this->currentId)->addError($e->getMessage());

            return parent::processValue($value, $isRoot);
        }
    }

    private function doProcessValue($value, $isRoot = false)
    {
        if ($value instanceof TypedReference) {
            if ($ref = $this->getAutowiredReference($value)) {
                return $ref;
            }
            $message = $this->createTypeNotFoundMessage($value, 'it');

            if (ContainerBuilder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $value->getInvalidBehavior()) {
                // since the error message varies by referenced id and $this->currentId, so should the id of the dummy errored definition
                $this->container->register($id = sprintf('.errored.%s.%s', $this->currentId, (string) $value), $value->getType())
                    ->addError($message);

                return new TypedReference($id, $value->getType(), $value->getInvalidBehavior(), $value->getName());
            }
            $this->container->log($this, $message);
        }
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            $this->container->log($this, sprintf('Skipping service "%s": Class or interface "%s" cannot be loaded.', $this->currentId, $value->getClass()));

            return $value;
        }

        $this->methodCalls = $value->getMethodCalls();

        try {
            $constructor = $this->getConstructor($value, false);
        } catch (RuntimeException $e) {
            throw new AutowiringFailedException($this->currentId, $e->getMessage(), 0, $e);
        }

        if ($constructor) {
            array_unshift($this->methodCalls, [$constructor, $value->getArguments()]);
        }

        $this->methodCalls = $this->autowireCalls($reflectionClass, $isRoot);

        if ($constructor) {
            list(, $arguments) = array_shift($this->methodCalls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($this->methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($this->methodCalls);
        }

        return $value;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     *
     * @return array
     */
    private function autowireCalls(\ReflectionClass $reflectionClass, bool $isRoot): array
    {
        $this->decoratedId = null;
        $this->decoratedClass = null;
        $this->getPreviousValue = null;

        if ($isRoot && ($definition = $this->container->getDefinition($this->currentId)) && $this->container->has($this->decoratedId = $definition->innerServiceId)) {
            $this->decoratedClass = $this->container->findDefinition($this->decoratedId)->getClass();
        }

        foreach ($this->methodCalls as $i => $call) {
            $this->decoratedMethodIndex = $i;
            list($method, $arguments) = $call;

            if ($method instanceof \ReflectionFunctionAbstract) {
                $reflectionMethod = $method;
            } else {
                $definition = new Definition($reflectionClass->name);
                try {
                    $reflectionMethod = $this->getReflectionMethod($definition, $method);
                } catch (RuntimeException $e) {
                    if ($definition->getFactory()) {
                        continue;
                    }
                    throw $e;
                }
            }

            $arguments = $this->autowireMethod($reflectionMethod, $arguments);

            if ($arguments !== $call[1]) {
                $this->methodCalls[$i][1] = $arguments;
            }
        }

        return $this->methodCalls;
    }

    /**
     * Autowires the constructor or a method.
     *
     * @return array The autowired arguments
     *
     * @throws AutowiringFailedException
     */
    private function autowireMethod(\ReflectionFunctionAbstract $reflectionMethod, array $arguments): array
    {
        $class = $reflectionMethod instanceof \ReflectionMethod ? $reflectionMethod->class : $this->currentId;
        $method = $reflectionMethod->name;
        $parameters = $reflectionMethod->getParameters();
        if ($reflectionMethod->isVariadic()) {
            array_pop($parameters);
        }

        foreach ($parameters as $index => $parameter) {
            if (\array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }

            $type = ProxyHelper::getTypeHint($reflectionMethod, $parameter, true);

            if (!$type) {
                if (isset($arguments[$index])) {
                    continue;
                }

                // no default value? Then fail
                if (!$parameter->isDefaultValueAvailable()) {
                    // For core classes, isDefaultValueAvailable() can
                    // be false when isOptional() returns true. If the
                    // argument *is* optional, allow it to be missing
                    if ($parameter->isOptional()) {
                        continue;
                    }
                    $type = ProxyHelper::getTypeHint($reflectionMethod, $parameter, false);
                    $type = $type ? sprintf('is type-hinted "%s"', ltrim($type, '\\')) : 'has no type-hint';

                    throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s()" %s, you should configure its value explicitly.', $this->currentId, $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method, $type));
                }

                // specifically pass the default value
                $arguments[$index] = $parameter->getDefaultValue();

                continue;
            }

            $getValue = function () use ($type, $parameter, $class, $method) {
                if (!$value = $this->getAutowiredReference($ref = new TypedReference($type, $type, ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, $parameter->name))) {
                    $failureMessage = $this->createTypeNotFoundMessage($ref, sprintf('argument "$%s" of method "%s()"', $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));

                    if ($parameter->isDefaultValueAvailable()) {
                        $value = $parameter->getDefaultValue();
                    } elseif (!$parameter->allowsNull()) {
                        throw new AutowiringFailedException($this->currentId, $failureMessage);
                    }
                    $this->container->log($this, $failureMessage);
                }

                return $value;
            };

            if ($this->decoratedClass && $isDecorated = is_a($this->decoratedClass, $type, true)) {
                if ($this->getPreviousValue) {
                    // The inner service is injected only if there is only 1 argument matching the type of the decorated class
                    // across all arguments of all autowired methods.
                    // If a second matching argument is found, the default behavior is restored.

                    $getPreviousValue = $this->getPreviousValue;
                    $this->methodCalls[$this->decoratedMethodIndex][1][$this->decoratedMethodArgumentIndex] = $getPreviousValue();
                    $this->decoratedClass = null; // Prevent further checks
                } else {
                    $arguments[$index] = new TypedReference($this->decoratedId, $this->decoratedClass);
                    $this->getPreviousValue = $getValue;
                    $this->decoratedMethodArgumentIndex = $index;

                    continue;
                }
            }

            $arguments[$index] = $getValue();
        }

        if ($parameters && !isset($arguments[++$index])) {
            while (0 <= --$index) {
                $parameter = $parameters[$index];
                if (!$parameter->isDefaultValueAvailable() || $parameter->getDefaultValue() !== $arguments[$index]) {
                    break;
                }
                unset($arguments[$index]);
            }
        }

        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments);

        return $arguments;
    }

    /**
     * @return TypedReference|null A reference to the service matching the given type, if any
     */
    private function getAutowiredReference(TypedReference $reference)
    {
        $this->lastFailure = null;
        $type = $reference->getType();

        if ($type !== (string) $reference) {
            return $reference;
        }

        if (null !== $name = $reference->getName()) {
            if ($this->container->has($alias = $type.' $'.$name) && !$this->container->findDefinition($alias)->isAbstract()) {
                return new TypedReference($alias, $type, $reference->getInvalidBehavior());
            }

            if ($this->container->has($name) && !$this->container->findDefinition($name)->isAbstract()) {
                foreach ($this->container->getAliases() as $id => $alias) {
                    if ($name === (string) $alias && 0 === strpos($id, $type.' $')) {
                        return new TypedReference($name, $type, $reference->getInvalidBehavior());
                    }
                }
            }
        }

        if ($this->container->has($type) && !$this->container->findDefinition($type)->isAbstract()) {
            return new TypedReference($type, $type, $reference->getInvalidBehavior());
        }
    }

    /**
     * Populates the list of available types.
     */
    private function populateAvailableTypes()
    {
        $this->types = [];
        $this->ambiguousServiceTypes = [];

        foreach ($this->container->getDefinitions() as $id => $definition) {
            $this->populateAvailableType($id, $definition);
        }
    }

    /**
     * Populates the list of available types for a given definition.
     */
    private function populateAvailableType(string $id, Definition $definition)
    {
        // Never use abstract services
        if ($definition->isAbstract()) {
            return;
        }

        if ('' === $id || '.' === $id[0] || $definition->isDeprecated() || !$reflectionClass = $this->container->getReflectionClass($definition->getClass(), false)) {
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
     */
    private function set(string $type, string $id)
    {
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
            $this->ambiguousServiceTypes[$type] = [$this->types[$type]];
            unset($this->types[$type]);
        }
        $this->ambiguousServiceTypes[$type][] = $id;
    }

    private function createTypeNotFoundMessage(TypedReference $reference, $label)
    {
        $trackResources = $this->container->isTrackingResources();
        $this->container->setResourceTracking(false);
        try {
            if ($r = $this->container->getReflectionClass($type = $reference->getType(), false)) {
                $alternatives = $this->createTypeAlternatives($reference);
            }
        } finally {
            $this->container->setResourceTracking($trackResources);
        }

        if (!$r) {
            // either $type does not exist or a parent class does not exist
            try {
                $resource = new ClassExistenceResource($type, false);
                // isFresh() will explode ONLY if a parent class/trait does not exist
                $resource->isFresh(0);
                $parentMsg = false;
            } catch (\ReflectionException $e) {
                $parentMsg = $e->getMessage();
            }

            $message = sprintf('has type "%s" but this class %s.', $type, $parentMsg ? sprintf('is missing a parent class (%s)', $parentMsg) : 'was not found');
        } else {
            $message = $this->container->has($type) ? 'this service is abstract' : 'no such service exists';
            $message = sprintf('references %s "%s" but %s.%s', $r->isInterface() ? 'interface' : 'class', $type, $message, $alternatives);

            if ($r->isInterface() && !$alternatives) {
                $message .= ' Did you create a class that implements this interface?';
            }
        }

        $message = sprintf('Cannot autowire service "%s": %s %s', $this->currentId, $label, $message);

        if (null !== $this->lastFailure) {
            $message = $this->lastFailure."\n".$message;
            $this->lastFailure = null;
        }

        return $message;
    }

    private function createTypeAlternatives(TypedReference $reference)
    {
        // try suggesting available aliases first
        if ($message = $this->getAliasesSuggestionForType($type = $reference->getType())) {
            return ' '.$message;
        }
        if (null === $this->ambiguousServiceTypes) {
            $this->populateAvailableTypes();
        }

        $servicesAndAliases = $this->container->getServiceIds();
        if (!$this->container->has($type) && false !== $key = array_search(strtolower($type), array_map('strtolower', $servicesAndAliases))) {
            return sprintf(' Did you mean "%s"?', $servicesAndAliases[$key]);
        } elseif (isset($this->ambiguousServiceTypes[$type])) {
            $message = sprintf('one of these existing services: "%s"', implode('", "', $this->ambiguousServiceTypes[$type]));
        } elseif (isset($this->types[$type])) {
            $message = sprintf('the existing "%s" service', $this->types[$type]);
        } else {
            return;
        }

        return sprintf(' You should maybe alias this %s to %s.', class_exists($type, false) ? 'class' : 'interface', $message);
    }

    private function getAliasesSuggestionForType($type, $extraContext = null)
    {
        $aliases = [];
        foreach (class_parents($type) + class_implements($type) as $parent) {
            if ($this->container->has($parent) && !$this->container->findDefinition($parent)->isAbstract()) {
                $aliases[] = $parent;
            }
        }

        $extraContext = $extraContext ? ' '.$extraContext : '';
        if (1 < $len = \count($aliases)) {
            $message = sprintf('Try changing the type-hint%s to one of its parents: ', $extraContext);
            for ($i = 0, --$len; $i < $len; ++$i) {
                $message .= sprintf('%s "%s", ', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);
            }
            $message .= sprintf('or %s "%s".', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);

            return $message;
        }

        if ($aliases) {
            return sprintf('Try changing the type-hint%s to "%s" instead.', $extraContext, $aliases[0]);
        }
    }
}
