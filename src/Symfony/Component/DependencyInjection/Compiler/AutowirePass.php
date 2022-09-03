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
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\MapDecorated;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Contracts\Service\Attribute\SubscribedService;

/**
 * Inspects existing service definitions and wires the autowired ones using the type hints of their classes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowirePass extends AbstractRecursivePass
{
    private array $types;
    private array $ambiguousServiceTypes;
    private array $autowiringAliases;
    private ?string $lastFailure = null;
    private bool $throwOnAutowiringException;
    private ?string $decoratedClass = null;
    private ?string $decoratedId = null;
    private ?array $methodCalls = null;
    private object $defaultArgument;
    private ?\Closure $getPreviousValue = null;
    private ?int $decoratedMethodIndex = null;
    private ?int $decoratedMethodArgumentIndex = null;
    private ?self $typesClone = null;
    private array $combinedAliases;

    public function __construct(bool $throwOnAutowireException = true)
    {
        $this->throwOnAutowiringException = $throwOnAutowireException;
        $this->defaultArgument = new class() {
            public $value;
            public $names;
        };
    }

    public function process(ContainerBuilder $container)
    {
        $this->populateCombinedAliases($container);

        try {
            $this->typesClone = clone $this;
            parent::process($container);
        } finally {
            $this->decoratedClass = null;
            $this->decoratedId = null;
            $this->methodCalls = null;
            $this->defaultArgument->names = null;
            $this->getPreviousValue = null;
            $this->decoratedMethodIndex = null;
            $this->decoratedMethodArgumentIndex = null;
            $this->typesClone = null;
            $this->combinedAliases = [];
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        try {
            return $this->doProcessValue($value, $isRoot);
        } catch (AutowiringFailedException $e) {
            if ($this->throwOnAutowiringException) {
                throw $e;
            }

            $this->container->getDefinition($this->currentId)->addError($e->getMessageCallback() ?? $e->getMessage());

            return parent::processValue($value, $isRoot);
        }
    }

    private function doProcessValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof TypedReference) {
            if ($attributes = $value->getAttributes()) {
                $attribute = array_pop($attributes);

                if ($attributes) {
                    throw new AutowiringFailedException($this->currentId, sprintf('Using multiple attributes with "%s" is not supported.', SubscribedService::class));
                }

                if (!$attribute instanceof Target) {
                    return $this->processAttribute($attribute, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $value->getInvalidBehavior());
                }

                $value = new TypedReference($value->getType(), $value->getType(), $value->getInvalidBehavior(), $attribute->name);
            }
            if ($ref = $this->getAutowiredReference($value, true)) {
                return $ref;
            }
            if (ContainerBuilder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $value->getInvalidBehavior()) {
                $message = $this->createTypeNotFoundMessageCallback($value, 'it');

                // since the error message varies by referenced id and $this->currentId, so should the id of the dummy errored definition
                $this->container->register($id = sprintf('.errored.%s.%s', $this->currentId, (string) $value), $value->getType())
                    ->addError($message);

                return new TypedReference($id, $value->getType(), $value->getInvalidBehavior(), $value->getName());
            }
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

        $checkAttributes = !$value->hasTag('container.ignore_attributes');
        $this->methodCalls = $this->autowireCalls($reflectionClass, $isRoot, $checkAttributes);

        if ($constructor) {
            [, $arguments] = array_shift($this->methodCalls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($this->methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($this->methodCalls);
        }

        return $value;
    }

    private function processAttribute(object $attribute, bool $isOptional = false): mixed
    {
        switch (true) {
            case $attribute instanceof Autowire:
                $value = $this->container->getParameterBag()->resolveValue($attribute->value);

                return $value instanceof Reference && $isOptional ? new Reference($value, ContainerInterface::NULL_ON_INVALID_REFERENCE) : $value;

            case $attribute instanceof TaggedIterator:
                return new TaggedIteratorArgument($attribute->tag, $attribute->indexAttribute, $attribute->defaultIndexMethod, false, $attribute->defaultPriorityMethod, (array) $attribute->exclude);

            case $attribute instanceof TaggedLocator:
                return new ServiceLocatorArgument(new TaggedIteratorArgument($attribute->tag, $attribute->indexAttribute, $attribute->defaultIndexMethod, true, $attribute->defaultPriorityMethod, (array) $attribute->exclude));

            case $attribute instanceof MapDecorated:
                $definition = $this->container->getDefinition($this->currentId);

                return new Reference($definition->innerServiceId ?? $this->currentId.'.inner', $definition->decorationOnInvalid ?? ContainerInterface::NULL_ON_INVALID_REFERENCE);
        }

        throw new AutowiringFailedException($this->currentId, sprintf('"%s" is an unsupported attribute.', $attribute::class));
    }

    private function autowireCalls(\ReflectionClass $reflectionClass, bool $isRoot, bool $checkAttributes): array
    {
        $this->decoratedId = null;
        $this->decoratedClass = null;
        $this->getPreviousValue = null;

        if ($isRoot && ($definition = $this->container->getDefinition($this->currentId)) && null !== ($this->decoratedId = $definition->innerServiceId) && $this->container->has($this->decoratedId)) {
            $this->decoratedClass = $this->container->findDefinition($this->decoratedId)->getClass();
        }

        $patchedIndexes = [];

        foreach ($this->methodCalls as $i => $call) {
            [$method, $arguments] = $call;

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

            $arguments = $this->autowireMethod($reflectionMethod, $arguments, $checkAttributes, $i);

            if ($arguments !== $call[1]) {
                $this->methodCalls[$i][1] = $arguments;
                $patchedIndexes[] = $i;
            }
        }

        // use named arguments to skip complex default values
        foreach ($patchedIndexes as $i) {
            $namedArguments = null;
            $arguments = $this->methodCalls[$i][1];

            foreach ($arguments as $j => $value) {
                if ($namedArguments && !$value instanceof $this->defaultArgument) {
                    unset($arguments[$j]);
                    $arguments[$namedArguments[$j]] = $value;
                }
                if ($namedArguments || !$value instanceof $this->defaultArgument) {
                    continue;
                }

                if (\is_array($value->value) ? $value->value : \is_object($value->value)) {
                    unset($arguments[$j]);
                    $namedArguments = $value->names;
                } else {
                    $arguments[$j] = $value->value;
                }
            }

            $this->methodCalls[$i][1] = $arguments;
        }

        return $this->methodCalls;
    }

    /**
     * Autowires the constructor or a method.
     *
     * @throws AutowiringFailedException
     */
    private function autowireMethod(\ReflectionFunctionAbstract $reflectionMethod, array $arguments, bool $checkAttributes, int $methodIndex): array
    {
        $class = $reflectionMethod instanceof \ReflectionMethod ? $reflectionMethod->class : $this->currentId;
        $method = $reflectionMethod->name;
        $parameters = $reflectionMethod->getParameters();
        if ($reflectionMethod->isVariadic()) {
            array_pop($parameters);
        }
        $this->defaultArgument->names = new \ArrayObject();

        foreach ($parameters as $index => $parameter) {
            $this->defaultArgument->names[$index] = $parameter->name;

            if (\array_key_exists($index, $arguments) && '' !== $arguments[$index]) {
                continue;
            }

            $type = ProxyHelper::exportType($parameter, true);

            if ($checkAttributes) {
                foreach ($parameter->getAttributes() as $attribute) {
                    if (\in_array($attribute->getName(), [TaggedIterator::class, TaggedLocator::class, Autowire::class, MapDecorated::class], true)) {
                        $arguments[$index] = $this->processAttribute($attribute->newInstance(), $parameter->allowsNull());

                        break;
                    }
                }

                if ('' !== ($arguments[$index] ?? '')) {
                    continue;
                }
            }

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
                        --$index;
                        break;
                    }
                    $type = ProxyHelper::exportType($parameter);
                    $type = $type ? sprintf('is type-hinted "%s"', preg_replace('/(^|[(|&])\\\\|^\?\\\\?/', '\1', $type)) : 'has no type-hint';

                    throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s()" %s, you should configure its value explicitly.', $this->currentId, $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method, $type));
                }

                // specifically pass the default value
                $arguments[$index] = clone $this->defaultArgument;
                $arguments[$index]->value = $parameter->getDefaultValue();

                continue;
            }

            $getValue = function () use ($type, $parameter, $class, $method) {
                if (!$value = $this->getAutowiredReference($ref = new TypedReference($type, $type, ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, Target::parseName($parameter)), false)) {
                    $failureMessage = $this->createTypeNotFoundMessageCallback($ref, sprintf('argument "$%s" of method "%s()"', $parameter->name, $class !== $this->currentId ? $class.'::'.$method : $method));

                    if ($parameter->isDefaultValueAvailable()) {
                        $value = clone $this->defaultArgument;
                        $value->value = $parameter->getDefaultValue();
                    } elseif (!$parameter->allowsNull()) {
                        throw new AutowiringFailedException($this->currentId, $failureMessage);
                    }
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
                    $this->decoratedMethodIndex = $methodIndex;
                    $this->decoratedMethodArgumentIndex = $index;

                    continue;
                }
            }

            $arguments[$index] = $getValue();
        }

        if ($parameters && !isset($arguments[++$index])) {
            while (0 <= --$index) {
                if (!$arguments[$index] instanceof $this->defaultArgument) {
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
     * Returns a reference to the service matching the given type, if any.
     */
    private function getAutowiredReference(TypedReference $reference, bool $filterType): ?TypedReference
    {
        $this->lastFailure = null;
        $type = $reference->getType();

        if ($type !== (string) $reference) {
            return $reference;
        }

        if ($filterType && false !== $m = strpbrk($type, '&|')) {
            $types = array_diff(explode($m[0], $type), ['int', 'string', 'array', 'bool', 'float', 'iterable', 'object', 'callable', 'null']);

            sort($types);

            $type = implode($m[0], $types);
        }

        if (null !== $name = $reference->getName()) {
            if ($this->container->has($alias = $type.' $'.$name) && !$this->container->findDefinition($alias)->isAbstract()) {
                return new TypedReference($alias, $type, $reference->getInvalidBehavior());
            }

            if (null !== ($alias = $this->combinedAliases[$alias] ?? null) && !$this->container->findDefinition($alias)->isAbstract()) {
                return new TypedReference($alias, $type, $reference->getInvalidBehavior());
            }

            if ($this->container->has($name) && !$this->container->findDefinition($name)->isAbstract()) {
                foreach ($this->container->getAliases() + $this->combinedAliases as $id => $alias) {
                    if ($name === (string) $alias && str_starts_with($id, $type.' $')) {
                        return new TypedReference($name, $type, $reference->getInvalidBehavior());
                    }
                }
            }
        }

        if ($this->container->has($type) && !$this->container->findDefinition($type)->isAbstract()) {
            return new TypedReference($type, $type, $reference->getInvalidBehavior());
        }

        if (null !== ($alias = $this->combinedAliases[$type] ?? null) && !$this->container->findDefinition($alias)->isAbstract()) {
            return new TypedReference($alias, $type, $reference->getInvalidBehavior());
        }

        return null;
    }

    /**
     * Populates the list of available types.
     */
    private function populateAvailableTypes(ContainerBuilder $container)
    {
        $this->types = [];
        $this->ambiguousServiceTypes = [];
        $this->autowiringAliases = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $this->populateAvailableType($container, $id, $definition);
        }

        foreach ($container->getAliases() as $id => $alias) {
            $this->populateAutowiringAlias($id);
        }
    }

    /**
     * Populates the list of available types for a given definition.
     */
    private function populateAvailableType(ContainerBuilder $container, string $id, Definition $definition)
    {
        // Never use abstract services
        if ($definition->isAbstract()) {
            return;
        }

        if ('' === $id || '.' === $id[0] || $definition->isDeprecated() || !$reflectionClass = $container->getReflectionClass($definition->getClass(), false)) {
            return;
        }

        foreach ($reflectionClass->getInterfaces() as $reflectionInterface) {
            $this->set($reflectionInterface->name, $id);
        }

        do {
            $this->set($reflectionClass->name, $id);
        } while ($reflectionClass = $reflectionClass->getParentClass());

        $this->populateAutowiringAlias($id);
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

    private function createTypeNotFoundMessageCallback(TypedReference $reference, string $label): \Closure
    {
        if (null === $this->typesClone->container) {
            $this->typesClone->container = new ContainerBuilder($this->container->getParameterBag());
            $this->typesClone->container->setAliases($this->container->getAliases());
            $this->typesClone->container->setDefinitions($this->container->getDefinitions());
            $this->typesClone->container->setResourceTracking(false);
        }
        $currentId = $this->currentId;

        return (function () use ($reference, $label, $currentId) {
            return $this->createTypeNotFoundMessage($reference, $label, $currentId);
        })->bindTo($this->typesClone);
    }

    private function createTypeNotFoundMessage(TypedReference $reference, string $label, string $currentId): string
    {
        $type = $reference->getType();

        $i = null;
        $namespace = $type;
        do {
            $namespace = substr($namespace, 0, $i);

            if ($this->container->hasDefinition($namespace) && $tag = $this->container->getDefinition($namespace)->getTag('container.excluded')) {
                return sprintf('Cannot autowire service "%s": %s needs an instance of "%s" but this type has been excluded %s.', $currentId, $label, $type, $tag[0]['source'] ?? 'from autowiring');
            }
        } while (false !== $i = strrpos($namespace, '\\'));

        if (!$r = $this->container->getReflectionClass($type, false)) {
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
            $alternatives = $this->createTypeAlternatives($this->container, $reference);
            $message = $this->container->has($type) ? 'this service is abstract' : 'no such service exists';
            $message = sprintf('references %s "%s" but %s.%s', $r->isInterface() ? 'interface' : 'class', $type, $message, $alternatives);

            if ($r->isInterface() && !$alternatives) {
                $message .= ' Did you create a class that implements this interface?';
            }
        }

        $message = sprintf('Cannot autowire service "%s": %s %s', $currentId, $label, $message);

        if (null !== $this->lastFailure) {
            $message = $this->lastFailure."\n".$message;
            $this->lastFailure = null;
        }

        return $message;
    }

    private function createTypeAlternatives(ContainerBuilder $container, TypedReference $reference): string
    {
        // try suggesting available aliases first
        if ($message = $this->getAliasesSuggestionForType($container, $type = $reference->getType())) {
            return ' '.$message;
        }
        if (!isset($this->ambiguousServiceTypes)) {
            $this->populateAvailableTypes($container);
        }

        $servicesAndAliases = $container->getServiceIds();
        if (null !== ($autowiringAliases = $this->autowiringAliases[$type] ?? null) && !isset($autowiringAliases[''])) {
            return sprintf(' Available autowiring aliases for this %s are: "$%s".', class_exists($type, false) ? 'class' : 'interface', implode('", "$', $autowiringAliases));
        }

        if (!$container->has($type) && false !== $key = array_search(strtolower($type), array_map('strtolower', $servicesAndAliases))) {
            return sprintf(' Did you mean "%s"?', $servicesAndAliases[$key]);
        } elseif (isset($this->ambiguousServiceTypes[$type])) {
            $message = sprintf('one of these existing services: "%s"', implode('", "', $this->ambiguousServiceTypes[$type]));
        } elseif (isset($this->types[$type])) {
            $message = sprintf('the existing "%s" service', $this->types[$type]);
        } else {
            return '';
        }

        return sprintf(' You should maybe alias this %s to %s.', class_exists($type, false) ? 'class' : 'interface', $message);
    }

    private function getAliasesSuggestionForType(ContainerBuilder $container, string $type): ?string
    {
        $aliases = [];
        foreach (class_parents($type) + class_implements($type) as $parent) {
            if ($container->has($parent) && !$container->findDefinition($parent)->isAbstract()) {
                $aliases[] = $parent;
            }
        }

        if (1 < $len = \count($aliases)) {
            $message = 'Try changing the type-hint to one of its parents: ';
            for ($i = 0, --$len; $i < $len; ++$i) {
                $message .= sprintf('%s "%s", ', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);
            }
            $message .= sprintf('or %s "%s".', class_exists($aliases[$i], false) ? 'class' : 'interface', $aliases[$i]);

            return $message;
        }

        if ($aliases) {
            return sprintf('Try changing the type-hint to "%s" instead.', $aliases[0]);
        }

        return null;
    }

    private function populateAutowiringAlias(string $id): void
    {
        if (!preg_match('/(?(DEFINE)(?<V>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))^((?&V)(?:\\\\(?&V))*+)(?: \$((?&V)))?$/', $id, $m)) {
            return;
        }

        $type = $m[2];
        $name = $m[3] ?? '';

        if (class_exists($type, false) || interface_exists($type, false)) {
            $this->autowiringAliases[$type][$name] = $name;
        }
    }

    private function populateCombinedAliases(ContainerBuilder $container): void
    {
        $this->combinedAliases = [];
        $reverseAliases = [];

        foreach ($container->getAliases() as $id => $alias) {
            if (!preg_match('/(?(DEFINE)(?<V>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))^((?&V)(?:\\\\(?&V))*+)(?: \$((?&V)))?$/', $id, $m)) {
                continue;
            }

            $type = $m[2];
            $name = $m[3] ?? '';
            $reverseAliases[(string) $alias][$name][] = $type;
        }

        foreach ($reverseAliases as $alias => $names) {
            foreach ($names as $name => $types) {
                if (2 > $count = \count($types)) {
                    continue;
                }
                sort($types);
                $i = 1 << $count;

                // compute the powerset of the list of types
                while ($i--) {
                    $set = [];
                    for ($j = 0; $j < $count; ++$j) {
                        if ($i & (1 << $j)) {
                            $set[] = $types[$j];
                        }
                    }

                    if (2 <= \count($set)) {
                        $this->combinedAliases[implode('&', $set).('' === $name ? '' : ' $'.$name)] = $alias;
                        $this->combinedAliases[implode('|', $set).('' === $name ? '' : ' $'.$name)] = $alias;
                    }
                }
            }
        }
    }
}
