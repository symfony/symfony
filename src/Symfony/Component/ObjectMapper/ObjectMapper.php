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
        $this->objectMap ??= new \WeakMap();
        try {
            return $this->doMap($source, $target, $this->objectMap);
        } finally {
            $this->objectMap = null;
        }
    }

    private function doMap(object $source, object|string|null $target, \WeakMap $objectMap): object
    {
        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapping($metadata, null, $source, null, null === $target);
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
            $map = $this->getMapping($metadata, null, $source, null, true);
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
        $targetConstructor = null;
        if (!$mappingToObject) {
            $targetConstructor = $targetRefl->getConstructor();

            foreach ($targetConstructor?->getParameters() ?? [] as $parameter) {
                $parameterName = $parameter->getName();

                if ($targetRefl->hasProperty($parameterName)) {
                    $property = $targetRefl->getProperty($parameterName);

                    if ($property->isReadOnly() && $property->isInitialized($mappedTarget)) {
                        continue;
                    }

                    if ($mappingToObject) {
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
        if ($refl === $targetRefl) {
            $readMetadataFrom = $mappedTarget;
        }

        $mapToProperties = [];
        foreach ($refl->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $mappings = $this->metadataFactory->create($readMetadataFrom, $propertyName);
            $mapSources = [$propertyName];

            foreach ($mappings as $mapping) {
                if (!$refl->hasProperty($propertyName) || !isset($source->$propertyName)) {
                    if (!empty($mapping->sources)) {
                        $mapSources = $mapping->sources;
                    } elseif (!empty($mapping->source)) {
                        $mapSources = [$mapping->source];
                    }
                }

                $targetPropertyName = $mapping->target ?? $propertyName;
                if (false === $if = $mapping->if) {
                    unset($ctorArguments[$targetPropertyName]);

                    continue;
                }

                if (
                    $if
                    && ($fn = $this->getCallable($if, $this->conditionCallableLocator, ConditionCallableInterface::class))
                    && $fn instanceof ClassRuleConditionCallableInterface
                    && !$this->call($fn, null, $source, $mappedTarget)
                ) {
                    continue;
                }

                $targetValue = null;

                foreach ($mapSources as $mapSource) {
                    $value = $this->getRawValue($source, $mapSource);
                    if ($if && $fn && !$this->call($fn, $value, $source, $mappedTarget)) {
                        unset($ctorArguments[$targetPropertyName]);

                        continue;
                    }

                    $targetValue = $ctorArguments[$targetPropertyName] ?? null;
                    if (null !== $mappedTarget && $this->isReadable($mappedTarget, $targetPropertyName)) {
                        $targetValue ??= $this->getRawValue($mappedTarget, $targetPropertyName);
                    }

                    $targetValue = $this->getSourceValue(
                        $source,
                        $mappedTarget,
                        $value,
                        $targetRefl->hasProperty($targetPropertyName)
                            ? $targetRefl->getProperty($targetPropertyName)
                            : null,
                        $targetValue,
                        $objectMap,
                        $mapping,
                    );

                    $this->storeValue($targetPropertyName, $mapToProperties, $ctorArguments, $targetValue);
                }
            }

            if ($mappings) {
                continue;
            }

            if ($targetRefl->hasProperty($propertyName)) {
                if (!$this->isReadable($source, $propertyName)) {
                    continue;
                }

                $sourceProperty = $refl->getProperty($propertyName);
                if ($refl->isInstance($source) && !$sourceProperty->isInitialized($source)) {
                    continue;
                }

                $targetValue = $ctorArguments[$propertyName] ?? null;
                if (
                    null !== $targetValue
                    && null !== $mappedTarget
                    && $this->isReadable($mappedTarget, $propertyName)
                ) {
                    $targetValue = $this->getRawValue($mappedTarget, $propertyName);
                }

                $value = $this->getSourceValue(
                    $source,
                    $mappedTarget,
                    $this->getRawValue($source, $propertyName),
                    $targetRefl->getProperty($propertyName),
                    $targetValue,
                    $objectMap
                );
                $this->storeValue($propertyName, $mapToProperties, $ctorArguments, $value);
                continue;
            }

            $rawValue = $this->getRawValue($source, $propertyName);
            if (
                \is_object($rawValue)
                && ($innerMetadata = $this->metadataFactory->create($rawValue))
                && ($mapTo = $this->getMapping($innerMetadata, $rawValue, $source, $mappedTarget, true))
                && \is_string($mapTo->target)
                && $mapTo->target === $targetRefl->getName()
            ) {
                ($this->objectMapper ?? $this)->map($rawValue, $mappedTarget);
            }
        }

        if (
            !$map?->transform
            && $targetConstructor
            && ($ctorArguments || !$targetConstructor->getNumberOfRequiredParameters())
        ) {
            try {
                $mappedTarget->__construct(...$ctorArguments);
            } catch (\ReflectionException $e) {
                throw new MappingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if ($mappingToObject && $ctorArguments) {
            foreach ($ctorArguments as $property => $value) {
                if ($this->propertyIsMappable($refl, $property) && $this->propertyIsMappable($targetRefl, $property)) {
                    $mapToProperties[$property] = $value;
                }
            }
        }

        foreach ($mapToProperties as $property => $value) {
            if ($this->propertyAccessor) {
                if ($this->propertyAccessor->isWritable($mappedTarget, $property)) {
                    $this->propertyAccessor->setValue($mappedTarget, $property, $value);
                }

                continue;
            }

            if (!$targetRefl->hasProperty($property)) {
                continue;
            }

            if ($targetRefl->getProperty($property)->isReadOnly()) {
                continue;
            }

            $targetRefl->getProperty($property)->setValue($mappedTarget, $value);
        }

        return $mappedTarget;
    }

    private function isReadable(object $source, string $propertyName): bool
    {
        if ($this->propertyAccessor) {
            return $this->propertyAccessor->isReadable($source, $propertyName);
        }

        if (!property_exists($source, $propertyName) || !isset($source->{$propertyName})) {
            return false;
        }

        return true;
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

        if (!property_exists($source, $propertyName) && !isset($source->{$propertyName})) {
            throw new NoSuchPropertyException(\sprintf('The property "%s" does not exist on "%s".', $propertyName, get_debug_type($source)));
        }

        return $source->{$propertyName};
    }

    private function getSourceValue(
        object $source,
        object $target,
        mixed $value,
        ?\ReflectionProperty $reflTargetProperty,
        mixed $targetValue,
        \WeakMap $objectMap,
        ?Mapping $mapping = null,
    ): mixed {
        // Source-based mapping
        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($target, $reflTargetProperty->getName()))
            && ($mapFrom = $this->getMapping($innerMetadata, null, $source, $target))
        ) {
            $value = $this->applyTransforms($mapFrom, $value, $source, $target);

            $value = ($this->objectMapper ?? $this)->map($value, $targetValue ?? $reflTargetProperty->getType()->getName());
        }

        // Property-based transformation
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source, $target, $targetValue);
        }

        // Target class-based mapping
        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value))
            && ($mapTo = $this->getMapping($innerMetadata, $value, $source, $target, true))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            $value = $this->applyTransforms($mapTo, $value, $source, $target, $targetValue);

            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->offsetExists($value)) {
                $value = $objectMap[$value];
            } else {
                if ($mapTo->transform) {
                    return $value;
                }

                $refl = new \ReflectionClass($mapTo->target);
                $mapper = $this->objectMapper ?? $this;

                return $refl->newLazyGhost(function ($target) use ($mapper, $value, $objectMap) {
                    $previousMap = $this->objectMap;
                    $this->objectMap = $objectMap;
                    try {
                        $objectMap[$value] = $mapper->map($value, $target);
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
    private function call(callable $fn, mixed $value, object $source, ?object $target = null, mixed $targetValue = null): mixed
    {
        if (\is_string($fn)) {
            return \call_user_func($fn, $value);
        }

        return $fn($value, $source, $target, $targetValue);
    }

    /**
     * @param Mapping[] $metadata
     */
    private function getMapping(array $metadata, mixed $value, object $source, ?object $target, bool $enforceUnique = false): ?Mapping
    {
        $mapping = null;
        foreach ($metadata as $mapAttribute) {
            if (
                $mapAttribute->if
                && ($fn = $this->getCallable($mapAttribute->if, $this->conditionCallableLocator, ConditionCallableInterface::class))
                && !$this->call($fn, $value, $source, $target)
            ) {
                continue;
            }

            if (
                $enforceUnique
                && null !== $mapping
                && $mapping->target === $mapAttribute->target
                && $mapping->sources === $mapAttribute->sources
            ) {
                if (null !== $value) {
                    throw new MappingException(\sprintf('Ambiguous mapping for "%s". Multiple #[Map] attributes match. Use the "if" parameter to specify conditions.', get_debug_type($value)));
                }

                throw new MappingException(\sprintf('Ambiguous mapping for "%s". Multiple #[Map] attributes match.', get_debug_type($source)));
            }

            if (!empty($mapping->sources) && !\in_array($source, $mapping->sources, true)) {
                continue;
            }

            if (!empty($mapping->target) && $target !== $mapping->target) {
                continue;
            }

            $mapping = $mapAttribute;
        }

        return $mapping;
    }

    private function applyTransforms(Mapping $map, mixed $value, object $source, ?object $target, mixed $targetValue = null): mixed
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
            $value = $this->call($fn, $value, $source, $target, $targetValue);
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
        } elseif ($refl->isUninitializedLazyObject($source)) {
            $refl->initializeLazyObject($source);
        }

        if ($metadata) {
            return $refl;
        }

        foreach ($refl->getProperties() as $property) {
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
