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
use Symfony\Component\ObjectMapper\Condition\TargetClass;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
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
    use ClassHierarchyTrait;

    /**
     * Tracks recursive references.
     */
    private ?\WeakMap $objectMap = null;

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
        private readonly ?ContainerInterface $transformCallableLocator = null,
        private readonly ?ContainerInterface $conditionCallableLocator = null,
        private ?ObjectMapperInterface $objectMapper = null,
    ) {
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
        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapTarget($this->filterMetadataByTarget($metadata, $target), null, $source, null, null === $target);
        $target ??= $map?->target;
        $mappingToObject = \is_object($target);

        if (!$target) {
            throw new MappingException(\sprintf('Mapping target not found for source "%s".', get_debug_type($source)));
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target class "%s" does not exist for source "%s".', $target, get_debug_type($source)));
        }

        try {
            $targetRefl = new \ReflectionClass($target);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        $mappedTarget = $mappingToObject ? $target : $targetRefl->newInstanceWithoutConstructor();

        if (!$metadata && $targetMetadata = $this->metadataFactory->create($mappedTarget)) {
            $metadata = $targetMetadata;
            $map = $this->getMapTarget($metadata, null, $source, null, false);
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
        $ctorArguments = [];
        $targetConstructor = $targetRefl->getConstructor();
        if (!$mappingToObject || $constructTarget) {
            foreach ($targetConstructor?->getParameters() ?? [] as $parameter) {
                $parameterName = $parameter->getName();

                if ($targetRefl->hasProperty($parameterName)) {
                    $property = $targetRefl->getProperty($parameterName);

                    if ($property->isReadOnly() && $property->isInitialized($mappedTarget)) {
                        continue;
                    }
                }

                if ($this->isReadable($source, $parameterName)) {
                    $ctorArguments[$parameterName] = $this->getRawValue($source, $parameterName);
                } else {
                    $ctorArguments[$parameterName] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                }
            }
        }

        $readMetadataFrom = $source;
        $refl = $this->getSourceReflectionClass($source) ?? $targetRefl;

        // When source contains no metadata, we read metadata on the target instead
        if ($readMetadataFromTarget = $refl === $targetRefl) {
            $readMetadataFrom = $mappedTarget;
        }

        $mapToProperties = [];
        $explicitTargets = [];
        $implicitValues = [];
        foreach ($this->getAllProperties($refl) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $mappings = $this->metadataFactory->create($readMetadataFrom, $propertyName);
            foreach ($mappings as $mapping) {
                // when metadata is read from the source, $mapping->source describes the
                // reverse mapping and must not be resolved against $source
                $sourcePropertyName = $readMetadataFromTarget ? $mapping->source ?? $propertyName : $propertyName;

                $targetPropertyName = $mapping->target ?? $propertyName;
                if (false === $if = $mapping->if) {
                    unset($ctorArguments[$targetPropertyName]);

                    continue;
                }

                if (
                    $if
                    && ($fn = $this->getCallable($if, $this->conditionCallableLocator))
                    && $fn instanceof TargetClass
                    && !$this->call($fn, null, $source, $mappedTarget)
                ) {
                    continue;
                }

                if (!$this->isReadable($source, $sourcePropertyName)
                    && $this->getPropertyFromHierarchy(new \ReflectionClass($source), $sourcePropertyName)
                ) {
                    continue;
                }

                $value = $this->getRawValue($source, $sourcePropertyName);
                if ($if && $fn && !$this->call($fn, $value, $source, $mappedTarget)) {
                    unset($ctorArguments[$targetPropertyName]);

                    continue;
                }

                $value = $this->getSourceValue($source, $mappedTarget, $value, $objectMap, $mapping, $targetPropertyName);
                $explicitTargets[$targetPropertyName] = true;
                $this->storeValue($targetPropertyName, $mapToProperties, $ctorArguments, $value);
            }

            if (!$mappings && $targetRefl->hasProperty($propertyName)) {
                if (!$this->isReadable($source, $propertyName)) {
                    continue;
                }

                $sourceProperty = $this->getPropertyFromHierarchy($refl, $propertyName);
                if ($sourceProperty && $refl->isInstance($source) && !$sourceProperty->isInitialized($source)) {
                    continue;
                }

                // when metadata is read from the source, an explicitly inbound #[Map] declared on the
                // target property itself is never surfaced above, so honor its transform here
                $sameNameMapping = $readMetadataFromTarget ? null : $this->getSameNameTargetMapping($mappedTarget, $propertyName);

                $implicitValues[$propertyName] = $this->getSourceValue($source, $mappedTarget, $this->getRawValue($source, $propertyName), $objectMap, $sameNameMapping, $propertyName);
            }
        }

        foreach ($implicitValues as $propertyName => $value) {
            if (!isset($explicitTargets[$propertyName])) {
                $this->storeValue($propertyName, $mapToProperties, $ctorArguments, $value);
            }
        }

        if ((!$mappingToObject || $constructTarget) && !$map?->transform && $targetConstructor
            && ($ctorArguments || !$targetConstructor->getNumberOfRequiredParameters())
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

            if (!$this->propertyIsMappable($targetRefl, $property)) {
                continue;
            }

            $mappedTarget->{$property} = $value;
        }

        return $mappedTarget;
    }

    private function isReadable(object $source, string $propertyName): bool
    {
        if ($this->propertyAccessor) {
            return $this->propertyAccessor->isReadable($source, $propertyName);
        }

        if (!property_exists($source, $propertyName)) {
            // only a private property declared by a parent class is invisible to property_exists();
            // like any other non-public property, it can only be read through magic __get()
            if ($this->getPropertyFromHierarchy($refl ??= new \ReflectionClass($source), $propertyName)) {
                return method_exists($source, '__get');
            }

            return isset($source->{$propertyName});
        }

        $refl = new \ReflectionClass($source);

        if (!$refl->hasProperty($propertyName)) {
            return true;
        }

        $property = $refl->getProperty($propertyName);

        if (!$property->isPublic()) {
            return method_exists($source, '__get');
        }

        return $property->isInitialized($source) || isset($source->{$propertyName});
    }

    private function getRawValue(object $source, string $propertyName): mixed
    {
        if ($this->propertyAccessor) {
            try {
                return $this->propertyAccessor->getValue($source, $propertyName);
            } catch (PropertyAccessorNoSuchPropertyException $e) {
                throw new NoSuchPropertyException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!property_exists($source, $propertyName) && !isset($source->{$propertyName})
            && !$this->getPropertyFromHierarchy(new \ReflectionClass($source), $propertyName)
        ) {
            throw new NoSuchPropertyException(\sprintf('The property "%s" does not exist on "%s".', $propertyName, get_debug_type($source)));
        }

        return $source->{$propertyName};
    }

    /**
     * Returns the unconditional #[Map] declared on the target's own property when it explicitly
     * describes an inbound same-name copy, so its transform still applies even when the iteration
     * reads metadata from the source side. A mapping that omits "source" describes how the property
     * is read when its own class is the source, and must not be applied in this direction.
     * Conditional mappings are left to the regular same-name copy, which does not evaluate
     * conditions.
     */
    private function getSameNameTargetMapping(object $target, string $propertyName): ?Mapping
    {
        foreach ($this->metadataFactory->create($target, $propertyName) as $mapping) {
            if (null === $mapping->if
                && $propertyName === $mapping->source
                && ($mapping->target ?? $propertyName) === $propertyName
            ) {
                return $mapping;
            }
        }

        return null;
    }

    private function getSourceValue(object $source, object $target, mixed $value, \WeakMap $objectMap, ?Mapping $mapping = null, ?string $targetPropertyName = null): mixed
    {
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source, $target);
        }

        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value))
            && ($innerMetadata = $this->filterMetadataByPropertyType($innerMetadata, $target, $targetPropertyName))
            && ($mapTo = $this->getMapTarget($innerMetadata, $value, $source, $target, true))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            if ($mapTo->transform) {
                return $this->applyTransforms($mapTo, $value, $source, $target);
            }

            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->offsetExists($value)) {
                $value = $objectMap[$value];
            } elseif (\PHP_VERSION_ID < 80400) {
                return ($this->objectMapper ?? $this)->map($value, $mapTo->target);
            } else {
                $refl = new \ReflectionClass($mapTo->target);
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
     * Store the value either the constructor arguments or as a property to be mapped.
     *
     * @param array<string, mixed> $mapToProperties
     * @param array<string, mixed> $ctorArguments
     */
    private function storeValue(string $propertyName, array &$mapToProperties, array &$ctorArguments, mixed $value): void
    {
        if (\array_key_exists($propertyName, $ctorArguments)) {
            $ctorArguments[$propertyName] = $value;

            return;
        }

        $mapToProperties[$propertyName] = $value;
    }

    /**
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
            if (($if = $mapAttribute->if) && ($fn = $this->getCallable($if, $this->conditionCallableLocator)) && !$this->call($fn, $value, $source, $target)) {
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
    private function filterMetadataByPropertyType(array $metadata, object $target, ?string $targetPropertyName): array
    {
        if (null === $targetPropertyName || !$metadata) {
            return $metadata;
        }

        if (!$property = $this->getPropertyFromHierarchy(new \ReflectionClass($target), $targetPropertyName)) {
            return $metadata;
        }

        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return $metadata;
        }

        $propertyClass = $type->getName();
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
            if ($fn = $this->getCallable($transform, $this->transformCallableLocator)) {
                if ($fn instanceof ObjectMapperAwareInterface) {
                    $fn = $fn->withObjectMapper($this->objectMapper ?? $this);
                }
                $value = $this->call($fn, $value, $source, $target);
            }
        }

        return $value;
    }

    /**
     * @param (string|callable(mixed $value, object $object): mixed) $fn
     */
    private function getCallable(string|callable $fn, ?ContainerInterface $locator = null): ?callable
    {
        if (\is_callable($fn)) {
            return $fn;
        }

        if ($locator?->has($fn)) {
            return $locator->get($fn);
        }

        return null;
    }

    /**
     * @return ?\ReflectionClass<object|T>
     */
    private function getSourceReflectionClass(object $source): ?\ReflectionClass
    {
        $metadata = $this->metadataFactory->create($source);
        try {
            $refl = new \ReflectionClass($source);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        if ($source instanceof LazyObjectInterface) {
            $source->initializeLazyObject();
        } elseif (\PHP_VERSION_ID >= 80400 && $refl->isUninitializedLazyObject($source)) {
            $refl->initializeLazyObject($source);
        }

        if ($metadata) {
            return $refl;
        }

        foreach ($this->getAllProperties($refl) as $property) {
            if ($this->metadataFactory->create($source, $property->getName())) {
                return $refl;
            }
        }

        return null;
    }

    private function propertyIsMappable(\ReflectionClass $targetRefl, int|string $property): bool
    {
        return $targetRefl->hasProperty($property) && $targetRefl->getProperty($property)->isPublic();
    }

    public function withObjectMapper(ObjectMapperInterface $objectMapper): static
    {
        $clone = clone $this;
        $clone->objectMapper = $objectMapper;

        return $clone;
    }
}
