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
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * @internal
 */
trait ObjectMapperTrait
{
    private ObjectMapperMetadataFactoryInterface $metadataFactory;
    private ?PropertyAccessorInterface $propertyAccessor = null;
    private ?ContainerInterface $transformCallableLocator = null;
    private ?ContainerInterface $conditionCallableLocator = null;

    /**
     * @param array<non-empty-string, array{defaultValue: mixed|null, hasDefault: bool, name: non-empty-string, propertyIsMappable: bool}> $constructorParams
     *
     * @return list<array{isConstructorParam: bool, mapping: Mapping|null, source: non-empty-string, target: string}>
     */
    private function analyzeProperties(
        \ReflectionClass $refl,
        object $readMetadataFrom,
        \ReflectionClass $sourceRefl,
        \ReflectionClass $targetRefl,
        object $source,
        array $constructorParams,
    ): array {
        $properties = [];
        foreach ($refl->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $propertyMappings = $this->metadataFactory->create($readMetadataFrom, $propertyName);

            foreach ($propertyMappings as $mapping) {
                if (false === $mapping->if) {
                    continue;
                }

                $sourcePropertyName = $propertyName;
                if ($mapping->source && (!$sourceRefl->hasProperty($propertyName) || !property_exists($source, $propertyName))) {
                    $sourcePropertyName = $mapping->source;
                }

                $targetPropertyName = $mapping->target ?? $propertyName;
                if (!$targetRefl->hasProperty($targetPropertyName)) {
                    continue;
                }

                $properties[] = [
                    'source' => $sourcePropertyName,
                    'target' => $targetPropertyName,
                    'mapping' => $mapping,
                    'isConstructorParam' => isset($constructorParams[$targetPropertyName]),
                ];
            }

            if (!$propertyMappings && $targetRefl->hasProperty($propertyName) && $sourceRefl->hasProperty($propertyName)) {
                $properties[] = [
                    'source' => $propertyName,
                    'target' => $propertyName,
                    'mapping' => null,
                    'isConstructorParam' => isset($constructorParams[$propertyName]),
                ];
            }
        }

        return $properties;
    }

    protected function checkCondition(Mapping $mapping, mixed $value, object $source, ?object $target): bool
    {
        if (($if = $mapping->if) && ($fn = MappingHelper::getCallable($if, $this->conditionCallableLocator)) && !MappingHelper::call($fn, $value, $source, $target)) {
            return false;
        }

        return true;
    }

    protected function applyTransforms(Mapping $map, mixed $value, object $source, ?object $target): mixed
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
            if ($fn = MappingHelper::getCallable($transform, $this->transformCallableLocator)) {
                $value = MappingHelper::call($fn, $value, $source, $target);
            }
        }

        return $value;
    }

    /**
     * @param Mapping[] $metadata
     */
    protected function getMapTarget(array $metadata, mixed $value, object $source, ?object $target): ?Mapping
    {
        $mapTo = null;
        foreach ($metadata as $mapAttribute) {
            if (false === $this->checkCondition($mapAttribute, $value, $source, $target)) {
                continue;
            }

            $mapTo = $mapAttribute;
        }

        return $mapTo;
    }

    /**
     * @return ?\ReflectionClass<object|T>
     */
    protected function getSourceReflectionClass(object $source): ?\ReflectionClass
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
}
