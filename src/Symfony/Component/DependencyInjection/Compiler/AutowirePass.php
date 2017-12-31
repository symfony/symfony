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
use Symfony\Component\DependencyInjection\Config\AutowireServiceResource;
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
    private $definedTypes = array();
    private $types;
    private $ambiguousServiceTypes = array();
    private $autowired = array();
    private $lastFailure;
    private $throwOnAutowiringException;
    private $autowiringExceptions = array();

    /**
     * @param bool $throwOnAutowireException If false, retrieved errors via getAutowiringExceptions
     */
    public function __construct($throwOnAutowireException = true)
    {
        $this->throwOnAutowiringException = $throwOnAutowireException;
    }

    /**
     * @return AutowiringFailedException[]
     */
    public function getAutowiringExceptions()
    {
        return $this->autowiringExceptions;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // clear out any possibly stored exceptions from before
        $this->autowiringExceptions = array();

        try {
            parent::process($container);
        } finally {
            $this->definedTypes = array();
            $this->types = null;
            $this->ambiguousServiceTypes = array();
            $this->autowired = array();
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
        @trigger_error('The '.__METHOD__.'() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use ContainerBuilder::getReflectionClass() instead.', E_USER_DEPRECATED);

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
        try {
            return $this->doProcessValue($value, $isRoot);
        } catch (AutowiringFailedException $e) {
            if ($this->throwOnAutowiringException) {
                throw $e;
            }

            $this->autowiringExceptions[] = $e;

            return parent::processValue($value, $isRoot);
        }
    }

    private function doProcessValue($value, $isRoot = false)
    {
        if ($value instanceof TypedReference) {
            if ($ref = $this->getAutowiredReference($value, $value->getRequiringClass() ? sprintf('for "%s" in "%s"', $value->getType(), $value->getRequiringClass()) : '')) {
                return $ref;
            }
            $this->container->log($this, $this->createTypeNotFoundMessage($value, 'it'));
        }
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            $this->container->log($this, sprintf('Skipping service "%s": Class or interface "%s" cannot be loaded.', $this->currentId, $value->getClass()));

            return $value;
        }

        $autowiredMethods = $this->getMethodsToAutowire($reflectionClass);
        $methodCalls = $value->getMethodCalls();

        try {
            $constructor = $this->getConstructor($value, false);
        } catch (RuntimeException $e) {
            throw new AutowiringFailedException($this->currentId, $e->getMessage(), 0, $e);
        }

        if ($constructor) {
            array_unshift($methodCalls, array($constructor, $value->getArguments()));
        }

        $methodCalls = $this->autowireCalls($reflectionClass, $methodCalls, $autowiredMethods);

        if ($constructor) {
            list(, $arguments) = array_shift($methodCalls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($methodCalls);
        }

        return $value;
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
        $methodsToAutowire = array();

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $r = $reflectionMethod;

            if ($r->isConstructor()) {
                continue;
            }

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

            if ($method instanceof \ReflectionFunctionAbstract) {
                $reflectionMethod = $method;
            } elseif (isset($autowiredMethods[$lcMethod = strtolower($method)]) && $autowiredMethods[$lcMethod]->isPublic()) {
                $reflectionMethod = $autowiredMethods[$lcMethod];
                unset($autowiredMethods[$lcMethod]);
            } else {
                $reflectionMethod = $this->getReflectionMethod(new Definition($reflectionClass->name), $method);
            }

            $arguments = $this->autowireMethod($reflectionMethod, $arguments);

            if ($arguments !== $call[1]) {
                $methodCalls[$i][1] = $arguments;
            }
        }

        foreach ($autowiredMethods as $lcMethod => $reflectionMethod) {
            $method = $reflectionMethod->name;

            if (!$reflectionMethod->isPublic()) {
                $class = $reflectionClass->name;
                throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": method "%s()" must be public.', $this->currentId, $class !== $this->currentId ? $class.'::'.$method : $method));
            }
            $methodCalls[] = array($method, $this->autowireMethod($reflectionMethod, array()));
        }

        return $methodCalls;
    }

    /**
     * Autowires the constructor or a method.
     *
     * @param \ReflectionFunctionAbstract $reflectionMethod
     * @param array                       $arguments
     *
     * @return array The autowired arguments
     *
     * @throws AutowiringFailedException
     */
    private function autowireMethod(\ReflectionFunctionAbstract $reflectionMethod, array $arguments)
    {
        $class = $reflectionMethod instanceof \ReflectionMethod ? $reflectionMethod->class : $this->currentId;
        $method = $reflectionMethod->name;
        $parameters = $reflectionMethod->getParameters();
        if (method_exists('ReflectionMethod', 'isVariadic') && $reflectionMethod->isVariadic()) {
            array_pop($parameters);
        }

        foreach ($parameters as $index => $parameter) {
            if (array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
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
                    throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s()" must have a type-hint or be given a value explicitly.', $this->currentId, $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));
                }

                // specifically pass the default value
                $arguments[$index] = $parameter->getDefaultValue();

                continue;
            }

            if (!$value = $this->getAutowiredReference($ref = new TypedReference($type, $type, !$parameter->isOptional() ? $class : ''), 'for '.sprintf('argument "$%s" of method "%s()"', $parameter->name, $class.'::'.$method))) {
                $failureMessage = $this->createTypeNotFoundMessage($ref, sprintf('argument "$%s" of method "%s()"', $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));

                if ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                } elseif (!$parameter->allowsNull()) {
                    throw new AutowiringFailedException($this->currentId, $failureMessage);
                }
                $this->container->log($this, $failureMessage);
            }

            $arguments[$index] = $value;
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
    private function getAutowiredReference(TypedReference $reference, $deprecationMessage)
    {
        $this->lastFailure = null;
        $type = $reference->getType();

        if ($type !== (string) $reference || ($this->container->has($type) && !$this->container->findDefinition($type)->isAbstract())) {
            return $reference;
        }

        if (null === $this->types) {
            $this->populateAvailableTypes();
        }

        if (isset($this->definedTypes[$type])) {
            return new TypedReference($this->types[$type], $type);
        }

        if (isset($this->types[$type])) {
            $message = 'Autowiring services based on the types they implement is deprecated since Symfony 3.3 and won\'t be supported in version 4.0.';
            if ($aliasSuggestion = $this->getAliasesSuggestionForType($type = $reference->getType(), $deprecationMessage)) {
                $message .= ' '.$aliasSuggestion;
            } else {
                $message .= sprintf(' You should %s the "%s" service to "%s" instead.', isset($this->types[$this->types[$type]]) ? 'alias' : 'rename (or alias)', $this->types[$type], $type);
            }

            @trigger_error($message, E_USER_DEPRECATED);

            return new TypedReference($this->types[$type], $type);
        }

        if (!$reference->canBeAutoregistered() || isset($this->types[$type]) || isset($this->ambiguousServiceTypes[$type])) {
            return;
        }

        if (isset($this->autowired[$type])) {
            return $this->autowired[$type] ? new TypedReference($this->autowired[$type], $type) : null;
        }

        return $this->createAutowiredDefinition($type);
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

        if ($definition->isDeprecated() || !$reflectionClass = $this->container->getReflectionClass($definition->getClass(), false)) {
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
     * @param string $type
     *
     * @return TypedReference|null A reference to the registered definition
     */
    private function createAutowiredDefinition($type)
    {
        if (!($typeHint = $this->container->getReflectionClass($type, false)) || !$typeHint->isInstantiable()) {
            return;
        }

        $currentId = $this->currentId;
        $this->currentId = $type;
        $this->autowired[$type] = $argumentId = sprintf('autowired.%s', $type);
        $argumentDefinition = new Definition($type);
        $argumentDefinition->setPublic(false);
        $argumentDefinition->setAutowired(true);

        try {
            $originalThrowSetting = $this->throwOnAutowiringException;
            $this->throwOnAutowiringException = true;
            $this->processValue($argumentDefinition, true);
            $this->container->setDefinition($argumentId, $argumentDefinition);
        } catch (AutowiringFailedException $e) {
            $this->autowired[$type] = false;
            $this->lastFailure = $e->getMessage();
            $this->container->log($this, $this->lastFailure);

            return;
        } finally {
            $this->throwOnAutowiringException = $originalThrowSetting;
            $this->currentId = $currentId;
        }

        $this->container->log($this, sprintf('Type "%s" has been auto-registered for service "%s".', $type, $this->currentId));

        return new TypedReference($argumentId, $type);
    }

    private function createTypeNotFoundMessage(TypedReference $reference, $label)
    {
        if (!$r = $this->container->getReflectionClass($type = $reference->getType(), false)) {
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
            $message = sprintf('references %s "%s" but %s.%s', $r->isInterface() ? 'interface' : 'class', $type, $message, $this->createTypeAlternatives($reference));
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

        if (isset($this->ambiguousServiceTypes[$type])) {
            $message = sprintf('one of these existing services: "%s"', implode('", "', $this->ambiguousServiceTypes[$type]));
        } elseif (isset($this->types[$type])) {
            $message = sprintf('the existing "%s" service', $this->types[$type]);
        } elseif ($reference->getRequiringClass() && !$reference->canBeAutoregistered()) {
            return ' It cannot be auto-registered because it is from a different root namespace.';
        } else {
            return;
        }

        return sprintf(' You should maybe alias this %s to %s.', class_exists($type, false) ? 'class' : 'interface', $message);
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

    private function getAliasesSuggestionForType($type, $extraContext = null)
    {
        $aliases = array();
        foreach (class_parents($type) + class_implements($type) as $parent) {
            if ($this->container->has($parent) && !$this->container->findDefinition($parent)->isAbstract()) {
                $aliases[] = $parent;
            }
        }

        $extraContext = $extraContext ? ' '.$extraContext : '';
        if (1 < $len = count($aliases)) {
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
