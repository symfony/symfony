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
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Internal\ObjectMapperTrait;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException as PropertyAccessorNoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Object to object mapper.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ObjectMapper implements ObjectMapperInterface, ObjectMapperAwareInterface
{
    use ObjectMapperAwareTrait;
    use ObjectMapperTrait;

    /**
     * Tracks recursive references.
     */
    private ?\SplObjectStorage $objectMap = null;

    public function __construct(
        ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ContainerInterface $transformCallableLocator = null,
        ?ContainerInterface $conditionCallableLocator = null,
        ?ObjectMapperInterface $objectMapper = null,
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->propertyAccessor = $propertyAccessor;
        $this->transformCallableLocator = $transformCallableLocator;
        $this->conditionCallableLocator = $conditionCallableLocator;
        $this->objectMapper = $objectMapper;
    }

    public function map(object $source, object|string|null $target = null): object
    {
        $objectMapInitialized = false;
        if (null === $this->objectMap) {
            $this->objectMap = new \SplObjectStorage();
            $objectMapInitialized = true;
        }

        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapTarget($metadata, null, $source, null);
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
            $map = $this->getMapTarget($metadata, null, $source, null);
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

        $this->objectMap[$source] = $mappedTarget;
        $ctorArguments = [];
        $constructor = $targetRefl->getConstructor();
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isPromoted()) {
                continue;
            }

            $parameterName = $parameter->getName();
            $property = $targetRefl->getProperty($parameterName);

            if ($property->isReadOnly() && $property->isInitialized($mappedTarget)) {
                continue;
            }

            // this may be filled later on see storeValue
            $ctorArguments[$parameterName] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        $readMetadataFrom = $source;
        $sourceRefl = $this->getSourceReflectionClass($source);
        $refl = $sourceRefl ?? $targetRefl;

        // When source contains no metadata, we read metadata on the target instead
        if ($refl === $targetRefl) {
            $readMetadataFrom = $mappedTarget;
        }

        $properties = $this->analyzeProperties($refl, $readMetadataFrom, $sourceRefl ?? $refl, $targetRefl, $source, $ctorArguments);
        $mapToProperties = [];

        foreach ($properties as ['source' => $sourceProperty, 'target' => $targetProperty, 'mapping' => $mapping]) {
            if ($sourceRefl?->hasProperty($sourceProperty) && !$sourceRefl->getProperty($sourceProperty)->isInitialized($source)) {
                continue;
            }

            $value = $this->getRawValue($source, $sourceProperty);

            if ($mapping && false === $this->checkCondition($mapping, $value, $source, $mappedTarget)) {
                continue;
            }

            $value = $this->getSourceValue($source, $mappedTarget, $value, $this->objectMap, $mapping);
            $this->storeValue($targetProperty, $mapToProperties, $ctorArguments, $value);
        }

        if (!$mappingToObject && !$map?->transform && $constructor) {
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
            MappingHelper::setValue($mappedTarget, $property, $value, $this->propertyAccessor);
        }

        if ($objectMapInitialized) {
            $this->objectMap = null;
        }

        return $mappedTarget;
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

    private function getSourceValue(object $source, object $target, mixed $value, \SplObjectStorage $objectMap, ?Mapping $mapping = null): mixed
    {
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source, $target);
        }

        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value))
            && ($mapTo = $this->getMapTarget($innerMetadata, $value, $source, $target))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            $value = $this->applyTransforms($mapTo, $value, $source, $target);

            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->offsetExists($value)) {
                $value = $objectMap[$value];
            } else {
                $value = ($this->objectMapper ?? $this)->map($value, $mapTo->target);
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
}
