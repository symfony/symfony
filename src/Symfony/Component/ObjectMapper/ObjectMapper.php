<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Condition\ClassRuleConditionCallableInterface;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchCallableException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\ClassMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\PropertyMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\PropertyNameCollectionFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\PropertyReadability;
use Symfony\Component\ObjectMapper\Metadata\PropertyTypeMappingMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException as PropertyAccessorNoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Object to object mapper.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ObjectMapper implements ObjectMapperInterface, ObjectMapperAwareInterface
{
    /**
     * Tracks recursive references.
     */
    private ?\WeakMap $objectMap = null;

    private readonly ClassMetadataFactoryInterface $classMetadataFactory;
    private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory;
    private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory;

    /**
     * @var array<class-string, \ReflectionClass>
     */
    private array $reflectionClasses = [];

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory = new PropertyTypeMappingMetadataFactory(new ReflectionObjectMapperMetadataFactory()),
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
        private readonly ?ContainerInterface $transformCallableLocator = null,
        private readonly ?ContainerInterface $conditionCallableLocator = null,
        private ?ObjectMapperInterface $objectMapper = null,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory = null,
        ?PropertyMetadataFactoryInterface $propertyMetadataFactory = null,
    ) {
        $this->classMetadataFactory = $classMetadataFactory ?? new ReflectionClassMetadataFactory($metadataFactory);
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory ?? new ReflectionPropertyNameCollectionFactory();
        $this->propertyMetadataFactory = $propertyMetadataFactory ?? new ReflectionPropertyMetadataFactory($metadataFactory, $this->classMetadataFactory, $propertyAccessor);
    }

    public function map(object $source, object|string|null $target = null): object
    {
        if ($this->objectMap) {
            return $this->doMap($source, $target, $this->objectMap);
        }

        $this->objectMap = new \WeakMap();
        try {
            return $this->doMap($source, $target, $this->objectMap);
        } finally {
            $this->objectMap = null;
        }
    }

    private function doMap(object $source, object|string|null $target, \WeakMap $objectMap, bool $constructTarget = false): object
    {
        // the class metadata is keyed by the target too: resolving it from the source alone still needs the metadata factory
        $metadata = null === $target ? $this->metadataFactory->create($source) : null;
        $map = null === $target ? $this->getMapTarget($metadata, null, $source, null, true) : null;
        $target ??= $map?->target;
        $mappingToObject = \is_object($target);

        if (!$target) {
            throw new MappingException(\sprintf('Mapping target not found for source "%s".', get_debug_type($source)));
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target class "%s" does not exist for source "%s".', $target, get_debug_type($source)));
        }

        $targetRefl = $this->getReflectionClass($target);
        $declaredTarget = $mappedTarget = $mappingToObject ? $target : $targetRefl->newInstanceWithoutConstructor();
        $classMetadata = $this->classMetadataFactory->create($source, $declaredTarget);

        if (null === $metadata) {
            $metadata = $classMetadata['classMappings'];
            $map = $this->getMapTarget($this->filterMetadataByTarget($metadata, $target), null, $source, null, false);
        }

        if (!$metadata && $classMetadata['classMappingsFromTarget']) {
            $map = $this->getMapTarget($classMetadata['classMappingsFromTarget'], null, $source, null, false);
        }

        if ($map && $map->transform) {
            $mappedTarget = $this->applyTransforms($map, $mappedTarget, $source, null);

            if (!\is_object($mappedTarget)) {
                throw new MappingTransformException(\sprintf('Cannot map "%s" to a non-object target of type "%s".', get_debug_type($source), get_debug_type($mappedTarget)));
            }
        }

        if (!is_a($mappedTarget, $targetRefl->getName(), false)) {
            throw new MappingException(\sprintf('Expected the mapped object to be an instance of "%s" but got "%s".', $targetRefl->getName(), get_debug_type($mappedTarget)));
        }

        $objectMap[$source] = $mappedTarget;
        $this->initializeLazyObject($source);
        $sourceVars = null;
        $ctorArguments = [];
        if (!$mappingToObject || $constructTarget) {
            foreach ($classMetadata['constructorParameters'] as $parameterName => $parameter) {
                if ($parameter['readOnly'] && $targetRefl->getProperty($parameterName)->isInitialized($mappedTarget)) {
                    continue;
                }

                $propertyMetadata = $this->propertyMetadataFactory->create($source, $declaredTarget, $parameterName);
                if ($this->isReadable($source, $parameterName, $propertyMetadata['readability'], $sourceVars)) {
                    $ctorArguments[$parameterName] = $this->getRawValue($source, $parameterName, $propertyMetadata['sourceDeclared']);
                } else {
                    $ctorArguments[$parameterName] = $parameter['hasDefault'] ? $parameter['default'] : null;
                }
            }
        }

        $mapToProperties = [];
        $targetName = $targetRefl->getName();
        $explicitTargets = [];
        $implicitValues = [];
        foreach ($this->propertyNameCollectionFactory->create($classMetadata['readMetadataFromTarget'] ? $declaredTarget : $source) as $propertyName) {
            $propertyMetadata = $this->propertyMetadataFactory->create($source, $declaredTarget, $propertyName);
            foreach ($propertyMetadata['mappings'] as $propertyMapping) {
                $mapping = $propertyMapping['mapping'];
                $sourcePropertyName = $propertyMapping['sourceProperty'];
                $targetPropertyName = $propertyMapping['targetProperty'];
                if (false === $if = $mapping->if) {
                    unset($ctorArguments[$targetPropertyName]);

                    continue;
                }

                $fn = null;
                $isClassRule = false;
                if ($if) {
                    $fn = $this->getCallable($if, $this->conditionCallableLocator, ConditionCallableInterface::class);
                    $isClassRule = $fn instanceof ClassRuleConditionCallableInterface;
                    if ($isClassRule && !$this->call($fn, null, $source, $mappedTarget)) {
                        continue;
                    }
                }

                if ($propertyMapping['sourceDeclared'] && !$this->isReadable($source, $sourcePropertyName, $propertyMapping['sourceReadability'], $sourceVars)) {
                    continue;
                }

                $value = $this->getRawValue($source, $sourcePropertyName, $propertyMapping['sourceDeclared']);
                if ($fn && !$isClassRule && !$this->call($fn, $value, $source, $mappedTarget)) {
                    unset($ctorArguments[$targetPropertyName]);

                    continue;
                }

                $value = $this->getSourceValue($source, $mappedTarget, $value, $objectMap, $mapping, $propertyMapping['targetPropertyClass'], $declaredTarget, $targetPropertyName, $ctorArguments, $propertyMapping['targetWritable']);
                $explicitTargets[$targetPropertyName] = true;
                $this->storeValue($targetPropertyName, $mapToProperties, $ctorArguments, $value, $propertyMapping['targetWritable']);
            }

            if ($propertyMetadata['mappings']) {
                continue;
            }

            if ($propertyMetadata['targetHasProperty']) {
                if (!$this->isReadable($source, $propertyName, $propertyMetadata['readability'], $sourceVars)) {
                    continue;
                }

                if (null !== $propertyMetadata['initializedKey'] && !\array_key_exists($propertyMetadata['initializedKey'], $sourceVars ??= (array) $source)) {
                    continue;
                }

                $implicitValues[$propertyName] = [$this->getSourceValue($source, $mappedTarget, $this->getRawValue($source, $propertyName, $propertyMetadata['sourceDeclared']), $objectMap, $propertyMetadata['sameNameMapping'], $propertyMetadata['targetPropertyClass'], $declaredTarget, $propertyName, $ctorArguments, $propertyMetadata['targetWritable']), $propertyMetadata['targetWritable']];

                continue;
            }

            if (!$this->isReadable($source, $propertyName, $propertyMetadata['mergeReadability'], $sourceVars)) {
                continue;
            }

            $rawValue = $this->getRawValue($source, $propertyName, $propertyMetadata['sourceDeclared']);
            if (
                \is_object($rawValue)
                // a self-referencing relation maps to the same target by definition, merging it would overwrite the target with the related object's values
                && !$rawValue instanceof $source
                && !$objectMap->offsetExists($rawValue)
                && ($innerMetadata = $this->metadataFactory->create($rawValue))
                && array_any($innerMetadata, static fn (Mapping $m): bool => \is_string($m->target) && is_a($targetName, $m->target, true))
            ) {
                ($this->objectMapper ?? $this)->map($rawValue, $mappedTarget);
            }
        }

        foreach ($implicitValues as $propertyName => [$value, $writable]) {
            if (!isset($explicitTargets[$propertyName])) {
                $this->storeValue($propertyName, $mapToProperties, $ctorArguments, $value, $writable);
            }
        }

        if ((!$mappingToObject || $constructTarget) && !$map?->transform && $classMetadata['hasConstructor']
            && ($ctorArguments || !$classMetadata['requiredConstructorParameters'])
        ) {
            try {
                $mappedTarget->__construct(...$ctorArguments);
            } catch (\ReflectionException $e) {
                throw new MappingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        foreach ($mapToProperties as $property => $value) {
            if ($this->propertyAccessor) {
                if ($this->propertyAccessor->isWritable($mappedTarget, $property)) {
                    $this->propertyAccessor->setValue($mappedTarget, $property, $value);
                }

                continue;
            }

            $mappedTarget->{$property} = $value;
        }

        return $mappedTarget;
    }

    /**
     * @param PropertyReadability::* $readability
     * @param ?array                 $sourceVars  the array cast of the source, computed on first use and shared by the whole map() call
     */
    private function isReadable(object $source, string $propertyName, int $readability, ?array &$sourceVars): bool
    {
        return match ($readability) {
            PropertyReadability::ACCESSOR => $this->propertyAccessor->isReadable($source, $propertyName),
            PropertyReadability::ALWAYS => true,
            PropertyReadability::NEVER => false,
            PropertyReadability::INITIALIZED => \array_key_exists($propertyName, $sourceVars ??= (array) $source) || isset($source->{$propertyName}),
            PropertyReadability::DYNAMIC => property_exists($source, $propertyName) || isset($source->{$propertyName}),
        };
    }

    private function getRawValue(object $source, string $propertyName, bool $declared): mixed
    {
        if ($this->propertyAccessor) {
            try {
                return $this->propertyAccessor->getValue($source, $propertyName);
            } catch (PropertyAccessorNoSuchPropertyException $e) {
                throw new NoSuchPropertyException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!$declared && !property_exists($source, $propertyName) && !isset($source->{$propertyName})) {
            throw new NoSuchPropertyException(\sprintf('The property "%s" does not exist on "%s".', $propertyName, get_debug_type($source)));
        }

        return $source->{$propertyName};
    }

    /**
     * @param array<string, mixed> $ctorArguments
     */
    private function getSourceValue(object $source, object $target, mixed $value, \WeakMap $objectMap, ?Mapping $mapping, ?string $targetPropertyClass, object $declaredTarget, string $targetPropertyName, array $ctorArguments, bool $targetWritable): mixed
    {
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source, $target);
        }

        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value, null, $this->getDestinationContext($declaredTarget, $target, $targetPropertyName, $ctorArguments, $targetWritable)))
            && ($innerMetadata = $this->filterMetadataByPropertyType($innerMetadata, $targetPropertyClass))
            && ($mapTo = $this->getMapTarget($innerMetadata, $value, $source, $target, true))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            $value = $this->applyTransforms($mapTo, $value, $source, $target);

            // an already mapped source is reusable only when it matches the target resolved for this property
            if ($value === $source && $target instanceof $mapTo->target) {
                $value = $target;
            } elseif ($objectMap->offsetExists($value) && $objectMap[$value] instanceof $mapTo->target) {
                $value = $objectMap[$value];
            } else {
                if ($mapTo->transform) {
                    return $value;
                }

                $refl = $this->getReflectionClass($mapTo->target);
                $mapper = $this->objectMapper ?? $this;

                return $refl->newLazyGhost(function ($target) use ($mapper, $value, $objectMap) {
                    $previousMap = $this->objectMap;
                    $this->objectMap = $objectMap;
                    try {
                        // the ghost has not run a constructor yet, unlike a caller-supplied target
                        $objectMap[$value] = $mapper === $this
                            ? $this->doMap($value, $target, $objectMap, true)
                            : $mapper->map($value, $target);
                    } finally {
                        $this->objectMap = $previousMap;
                    }
                });
            }
        }

        return $value;
    }

    /**
     * Tells the metadata factory which property the nested value is written into, so that a factory
     * can resolve a mapping from the type of that property.
     *
     * The context is left empty when the mapper cannot write the property: a value that is thrown
     * away must not report a mapping error.
     *
     * @param array<string, mixed> $ctorArguments
     *
     * @return array<string, mixed>
     */
    private function getDestinationContext(object $declaredTarget, object $mappedTarget, string $propertyName, array $ctorArguments, bool $writable): array
    {
        $writable = \array_key_exists($propertyName, $ctorArguments)
            || ($this->propertyAccessor ? $this->propertyAccessor->isWritable($mappedTarget, $propertyName) : $writable);

        return $writable ? ['target' => $declaredTarget::class, 'target_property' => $propertyName] : [];
    }

    /**
     * Store the value either the constructor arguments or as a property to be mapped.
     *
     * A property that is not writable without a PropertyAccessor is dropped right away.
     *
     * @param array<string, mixed> $mapToProperties
     * @param array<string, mixed> $ctorArguments
     */
    private function storeValue(string $propertyName, array &$mapToProperties, array &$ctorArguments, mixed $value, bool $writable): void
    {
        if (\array_key_exists($propertyName, $ctorArguments)) {
            $ctorArguments[$propertyName] = $value;

            return;
        }

        if ($writable || $this->propertyAccessor) {
            $mapToProperties[$propertyName] = $value;
        }
    }

    /**
     * @param-immediately-invoked-callable $fn
     *
     * @param callable(): mixed $fn
     */
    private function call(callable $fn, mixed $value, object $source, ?object $target = null): mixed
    {
        if (\is_string($fn)) {
            return \call_user_func($fn, $value);
        }

        return $fn($value, $source, $target);
    }

    /**
     * @param Mapping[] $metadata
     */
    private function getMapTarget(array $metadata, mixed $value, object $source, ?object $target, bool $enforceUnique = false): ?Mapping
    {
        $mapTo = null;
        foreach ($metadata as $mapAttribute) {
            if (($if = $mapAttribute->if) && ($fn = $this->getCallable($if, $this->conditionCallableLocator, ConditionCallableInterface::class)) && !$this->call($fn, $value, $source, $target)) {
                continue;
            }

            if ($enforceUnique && null !== $mapTo) {
                throw new MappingException(\sprintf('Ambiguous mapping for "%s". Multiple #[Map] attributes match. Use the "if" parameter to specify conditions.', get_debug_type($value ?? $source)));
            }

            $mapTo = $mapAttribute;
        }

        return $mapTo;
    }

    /**
     * Narrows the class-level mappings of a source to the ones related to the target the caller asked for.
     *
     * A mapping declaring a subclass of that target is kept: its transform can still produce an
     * instance the caller accepts.
     *
     * @param Mapping[] $metadata
     *
     * @return Mapping[]
     */
    private function filterMetadataByTarget(array $metadata, object|string|null $target): array
    {
        if (null === $target) {
            return $metadata;
        }

        $targetClass = \is_object($target) ? $target::class : $target;

        return array_filter($metadata, static fn (Mapping $m): bool => null === $m->target || is_a($targetClass, $m->target, true) || is_a($m->target, $targetClass, true));
    }

    /**
     * Narrows the mappings of a nested object to the one matching the declared type of the property it is mapped into.
     *
     * A nested class declaring several #[Map] targets yields one Mapping per target. When the
     * destination property is typed, only one of them can be assigned to it, so the mapping is not ambiguous.
     * When none of them can be assigned to it, no mapping applies and the value is written as it is.
     * Mappings carrying a transform are kept: what lands in the property is the transform's return value,
     * not the declared target.
     *
     * @param Mapping[] $metadata
     *
     * @return Mapping[]
     */
    private function filterMetadataByPropertyType(array $metadata, ?string $propertyClass): array
    {
        if (null === $propertyClass || !$metadata) {
            return $metadata;
        }

        $filtered = array_values(array_filter($metadata, static fn (Mapping $m): bool => $m->transform || (\is_string($m->target)
            && class_exists($m->target)
            && is_a($m->target, $propertyClass, true))
        ));

        return 1 < \count($filtered) ? $metadata : $filtered;
    }

    private function applyTransforms(Mapping $map, mixed $value, object $source, ?object $target): mixed
    {
        if (!$transforms = $map->transform) {
            return $value;
        }

        if (\is_callable($transforms)) {
            $transforms = [$transforms];
        } elseif (!\is_array($transforms)) {
            $transforms = [$transforms];
        }

        foreach ($transforms as $transform) {
            $fn = $this->getCallable($transform, $this->transformCallableLocator, TransformCallableInterface::class);
            if ($fn instanceof ObjectMapperAwareInterface) {
                $fn = $fn->withObjectMapper($this->objectMapper ?? $this);
            }
            $value = $fn instanceof MappingAwareTransformCallableInterface
                ? $fn($value, $source, $target, $map)
                : $this->call($fn, $value, $source, $target);
        }

        return $value;
    }

    /**
     * @param (string|callable(mixed $value, object $object): mixed) $fn
     * @param class-string|null                                      $expectedInterface
     */
    private function getCallable(string|callable $fn, ?ContainerInterface $locator = null, ?string $expectedInterface = null): callable
    {
        if (\is_callable($fn)) {
            if ($expectedInterface && \is_object($fn) && !$fn instanceof $expectedInterface) {
                throw new NoSuchCallableException(\sprintf('"%s" is not a valid callable. Make sure it implements "%s".', get_debug_type($fn), $expectedInterface));
            }

            return $fn;
        }

        if ($locator?->has($fn)) {
            $callable = $locator->get($fn);

            if ($expectedInterface && !$callable instanceof $expectedInterface) {
                throw new NoSuchCallableException(\sprintf('"%s" is not a valid callable. Make sure it implements "%s".', $fn, $expectedInterface));
            }

            return $callable;
        }

        throw new NoSuchCallableException(\sprintf('"%s" is not a valid callable.', $fn).($expectedInterface ? \sprintf(' If you use a class, make sure it implements "%s".', $expectedInterface) : ''));
    }

    private function getReflectionClass(object|string $objectOrClass): \ReflectionClass
    {
        $class = \is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass;

        try {
            return $this->reflectionClasses[$class] ??= new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function initializeLazyObject(object $source): void
    {
        if ($source instanceof LazyObjectInterface) {
            $source->initializeLazyObject();
        } elseif (($refl = $this->getReflectionClass($source))->isUninitializedLazyObject($source)) {
            $refl->initializeLazyObject($source);
        }
    }

    public function withObjectMapper(ObjectMapperInterface $objectMapper): static
    {
        $clone = clone $this;
        $clone->objectMapper = $objectMapper;

        return $clone;
    }
}
