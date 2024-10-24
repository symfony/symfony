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

use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\ReflectionException;
use Symfony\Component\ObjectMapper\Metadata\MapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ReflectionMapperMetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Object to object mapper.
 *
 * @implements ObjectMapperInterface<T>
 *
 * @template T of object
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ObjectMapper implements ObjectMapperInterface
{
    public function __construct(
        private readonly MapperMetadataFactoryInterface $metadataFactory = new ReflectionMapperMetadataFactory(),
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
    }

    public function map(object $source, object|string|null $target = null): object
    {
        static $objectMap = null;
        $objectMapInitialized = false;

        if (null === $objectMap) {
            $objectMap = new \SplObjectStorage();
            $objectMapInitialized = true;
        }

        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapTarget($metadata, null, $source);
        $target ??= $map?->target;
        $mappingToObject = \is_object($target);

        if (!$target) {
            throw new MappingException('Mapping target not found.');
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target "%s" not found.', $target));
        }

        try {
            $targetRefl = new \ReflectionClass($target);
        } catch (\ReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }

        $mappedTarget = $mappingToObject ? $target : $targetRefl->newInstanceWithoutConstructor();
        if ($map && $map->transform) {
            $mappedTarget = $this->applyTransforms($map, $mappedTarget, $mappedTarget);

            if (!\is_object($mappedTarget)) {
                throw new MappingTransformException('Can not map to a non-object.');
            }
        }

        if (!is_a($mappedTarget, $targetRefl->getName(), false)) {
            throw new MappingException(\sprintf('Expected the mapped object to be an instance of "%s".', $mappingToObject ? $mappedTarget::class : $mappedTarget));
        }

        $objectMap[$source] = $mappedTarget;
        $ctorArguments = [];
        $constructor = $targetRefl->getConstructor();
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $parameterName = $parameter->getName();
            if (!$targetRefl->hasProperty($parameterName)) {
                continue;
            }

            $property = $targetRefl->getProperty($parameterName);

            // The mapped class was probably instantiated in a transform we can't write a readonly property
            if ($property->isReadOnly() && ($property->isInitialized($mappedTarget) && $property->getValue($mappedTarget))) {
                continue;
            }

            $ctorArguments[$parameterName] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        try {
            $refl = new \ReflectionClass($source);
        } catch (\ReflectionException $e) {
            throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
        }

        $mapToProperties = [];
        foreach ($refl->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $mappings = $this->metadataFactory->create($source, $propertyName);
            foreach ($mappings as $mapping) {
                if (($fn = $mapping->if) && !$this->call($fn, null, $source)) {
                    continue;
                }

                $targetPropertyName = $mapping->target ?? $propertyName;
                if (!$targetRefl->hasProperty($targetPropertyName)) {
                    continue;
                }

                $value = $this->getSourceValue($source, $mappedTarget, $propertyName, $objectMap, $mapping);
                $this->storeValue($targetPropertyName, $mapToProperties, $ctorArguments, $value);
            }

            if (!$mappings && $targetRefl->hasProperty($propertyName)) {
                $value = $this->getSourceValue($source, $mappedTarget, $propertyName, $objectMap);
                $this->storeValue($propertyName, $mapToProperties, $ctorArguments, $value);
            }
        }

        if (!$mappingToObject && $ctorArguments) {
            try {
                $constructor?->invokeArgs($mappedTarget, $ctorArguments);
            } catch (\ReflectionException $e) {
                throw new ReflectionException($e->getMessage(), $e->getCode(), $e);
            }
        }

        foreach ($mapToProperties as $property => $value) {
            $this->propertyAccessor ? $this->propertyAccessor->setValue($mappedTarget, $property, $value) : ($mappedTarget->{$property} = $value);
        }

        if ($objectMapInitialized) {
            $objectMap = null;
        }

        return $mappedTarget;
    }

    private function getSourceValue(object $source, object $target, string $propertyName, \SplObjectStorage $objectMap, ?Mapping $mapping = null): mixed
    {
        $value = $this->propertyAccessor ? $this->propertyAccessor->getValue($source, $propertyName) : $source->{$propertyName};
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source);
        }

        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value))
            && ($mapTo = $this->getMapTarget($innerMetadata, $value, $source))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            $value = $this->applyTransforms($mapTo, $value, $source);

            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->contains($value)) {
                $value = $objectMap[$value];
            } else {
                $value = $this->map($value, $mapTo->target);
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
    private function call(callable $fn, mixed $value, object $object): mixed
    {
        try {
            $refl = new \ReflectionFunction(\Closure::fromCallable($fn));
        } catch (\ReflectionException $e) {
            throw new ReflectionException($e);
        }

        $withParameters = $refl->getParameters();
        $withArgs = [$value];

        // Let's not send object if we don't need to, gives the ability to call native functions
        foreach ($withParameters as $parameter) {
            if ('object' === $parameter->getName()) {
                $withArgs['object'] = $object;
                break;
            }
        }

        return \call_user_func_array($fn, $withArgs);
    }

    /**
     * @param Mapping[] $metadata
     */
    private function getMapTarget(array $metadata, mixed $value, object $source): ?Mapping
    {
        $mapTo = null;
        foreach ($metadata as $mapAttribute) {
            if (($fn = $mapAttribute->if) && !$this->call($fn, $value, $source)) {
                continue;
            }

            $mapTo = $mapAttribute;
        }

        return $mapTo;
    }

    private function applyTransforms(Mapping $map, mixed $value, object $object): mixed
    {
        if (!($transforms = $map->transform)) {
            return $value;
        }

        if (\is_callable($transforms)) {
            $transforms = [$transforms];
        } elseif (!\is_array($transforms)) {
            $transforms = [$transforms];
        }

        foreach ($transforms as $transform) {
            if (\is_callable($transform)) {
                $value = $this->call($transform, $value, $object);
            }
        }

        return $value;
    }
}
