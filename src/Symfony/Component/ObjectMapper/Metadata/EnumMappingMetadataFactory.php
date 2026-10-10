<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Transform\MapEnum;

/**
 * @internal
 *
 * @author Julien Robic <nayte91@gmail.com>
 */
final class EnumMappingMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    private array $reflectionClassCache = [];
    private array $backingTypeCache = [];

    public function __construct(private readonly ObjectMapperMetadataFactoryInterface $inner)
    {
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        $mappings = $this->inner->create($object, $property, $context);

        if (!$property || !isset($context['source'], $context['target'])) {
            return $mappings;
        }

        $hasMappingForTarget = false;

        foreach ($mappings as $i => $mapping) {
            if ($mapping->targetClass && !is_a($context['target'], $mapping->targetClass, true)) {
                continue;
            }

            $hasMappingForTarget = true;

            // a transform receives the enum as is, and a disabled mapping writes nothing
            if (null !== $mapping->transform || false === $mapping->if) {
                continue;
            }

            [$sourceProperty, $targetProperty] = ($object::class === $context['source']) ?
                [$property, $mapping->target ?? $property] :
                [$mapping->source ?? $property, $property]
            ;

            $sourceType = $this->getPropertyTypeName($context['source'], $sourceProperty);
            $targetType = $this->getPropertyTypeName($context['target'], $targetProperty);

            if (!$sourceType || !$targetType) {
                continue;
            }

            if (!$mapEnum = $this->detectEnumMapping($sourceType, $targetType)) {
                continue;
            }

            $mappings[$i] = new Mapping($mapping->target, $mapping->source, $mapping->if, $mapEnum, $mapping->targetClass);
        }

        if (!$hasMappingForTarget) {
            return [...$mappings, ...$this->createEnumMappingIfNeeded($context['source'], $property, $context['target'], $property)];
        }

        return $mappings;
    }

    private function createEnumMappingIfNeeded(string $sourceClass, string $sourceProperty, string $targetClass, string $targetProperty): array
    {
        $sourceType = $this->getPropertyTypeName($sourceClass, $sourceProperty);
        $targetType = $this->getPropertyTypeName($targetClass, $targetProperty);

        if (!$sourceType || !$targetType) {
            return [];
        }

        $mapEnum = $this->detectEnumMapping($sourceType, $targetType);

        return $mapEnum ? [new Mapping(null, null, null, $mapEnum)] : [];
    }

    private function detectEnumMapping(string $sourceType, string $targetType): ?MapEnum
    {
        $validBackingTypes = ['int', 'string'];
        $sourceIsEnum = is_a($sourceType, \UnitEnum::class, true);
        $targetIsEnum = is_a($targetType, \UnitEnum::class, true);

        [$enumType, $scalarType] = match (true) {
            $sourceIsEnum && \in_array($targetType, $validBackingTypes, true) => [$sourceType, $targetType],
            $targetIsEnum && \in_array($sourceType, $validBackingTypes, true) => [$targetType, $sourceType],
            default => [null, null],
        };

        if (null === $enumType) {
            return null;
        }

        $backingType = $this->resolveBackingType($enumType);
        if (null === $backingType) {
            throw new MappingTransformException(\sprintf('Cannot convert enum "%s" to/from scalar. Only BackedEnum can be converted.', $enumType));
        }
        if ($backingType !== $scalarType) {
            throw new MappingTransformException(\sprintf('Type mismatch: enum "%s" is backed by "%s", but scalar type is "%s".', $enumType, $backingType, $scalarType));
        }

        return new MapEnum($targetType);
    }

    private function resolveBackingType(string $enumClass): ?string
    {
        if (\array_key_exists($enumClass, $this->backingTypeCache)) {
            return $this->backingTypeCache[$enumClass];
        }

        if (!is_a($enumClass, \BackedEnum::class, true)) {
            return $this->backingTypeCache[$enumClass] = null;
        }

        $backingType = (new \ReflectionEnum($enumClass))->getBackingType();

        return $this->backingTypeCache[$enumClass] = $backingType instanceof \ReflectionNamedType ? $backingType->getName() : null;
    }

    private function getPropertyTypeName(string $class, string $property): ?string
    {
        $refl = $this->reflectionClassCache[$class] ??= new \ReflectionClass($class);

        if (!$refl->hasProperty($property)) {
            return null;
        }

        $type = $refl->getProperty($property)->getType();

        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }
}
