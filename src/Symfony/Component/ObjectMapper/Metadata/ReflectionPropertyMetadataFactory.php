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

use Symfony\Component\ObjectMapper\ClassHierarchyTrait;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Metadata is cached per source and target class: the decorated factory is expected to derive its mappings from classes, not from instances.
 *
 * @phpstan-import-type PropertyMetadata from PropertyMetadataFactoryInterface
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    use ClassHierarchyTrait;

    /**
     * @var array<class-string, array<class-string, array<string, PropertyMetadata>>>
     */
    private array $metadata = [];

    /**
     * @var array<class-string, \ReflectionClass>
     */
    private array $reflectionClasses = [];

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory,
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
    }

    public function create(object $source, object $target, string $property): array
    {
        return $this->metadata[$source::class][$target::class][$property] ??= $this->doCreate($source, $target, $property);
    }

    /**
     * @return PropertyMetadata
     */
    private function doCreate(object $source, object $target, string $property): array
    {
        $sourceRefl = $this->reflectionClasses[$source::class] ??= new \ReflectionClass($source);
        $targetRefl = $this->reflectionClasses[$target::class] ??= new \ReflectionClass($target);
        $readMetadataFromTarget = $this->classMetadataFactory->create($source, $target)['readMetadataFromTarget'];
        $iteratedRefl = $readMetadataFromTarget ? $targetRefl : $sourceRefl;
        $hasMagicGet = $sourceRefl->hasMethod('__get');
        $targetName = $targetRefl->getName();

        $mappings = [];
        foreach ($this->metadataFactory->create($readMetadataFromTarget ? $target : $source, $property, ['source' => $source::class, 'target' => $targetName]) as $mapping) {
            if ($mapping->targetClass && !is_a($targetName, $mapping->targetClass, true)) {
                continue;
            }

            // when metadata is read from the source, $mapping->source describes the
            // reverse mapping and must not be resolved against $source
            $sourceProperty = $readMetadataFromTarget ? $mapping->source ?? $property : $property;
            $targetProperty = $mapping->target ?? $property;
            $declaredSourceProperty = $this->getPropertyFromHierarchy($sourceRefl, $sourceProperty);
            $mappings[] = [
                'mapping' => $mapping,
                'sourceProperty' => $sourceProperty,
                'targetProperty' => $targetProperty,
                'sourceReadability' => $this->getReadability($sourceRefl, $sourceRefl, $sourceProperty, $declaredSourceProperty, $hasMagicGet),
                'sourceDeclared' => null !== $declaredSourceProperty,
                'targetPropertyClass' => $this->getPropertyClass($targetRefl, $targetProperty),
                'targetWritable' => $this->isWritable($targetRefl, $targetProperty),
            ];
        }

        $targetHasProperty = $targetRefl->hasProperty($property);
        $declared = $this->getPropertyFromHierarchy($sourceRefl, $property);
        $iteratedProperty = $readMetadataFromTarget ? $this->getPropertyFromHierarchy($targetRefl, $property) : $declared;
        $readability = $this->getReadability($sourceRefl, $sourceRefl, $property, $declared, $hasMagicGet);

        return [
            'name' => $property,
            'sourceDeclared' => null !== $declared,
            'readability' => $readability,
            'mergeReadability' => $mappings || $targetHasProperty ? $readability : $this->getReadability($sourceRefl, $iteratedRefl, $property, $declared, $hasMagicGet),
            'mappings' => $mappings,
            'targetHasProperty' => $targetHasProperty,
            'initializedKey' => $iteratedProperty && $iteratedRefl->isInstance($source) ? $this->getArrayCastKey($iteratedProperty) : null,
            'sameNameMapping' => $targetHasProperty && !$readMetadataFromTarget ? $this->getSameNameTargetMapping($target, $property) : null,
            'targetPropertyClass' => $this->getPropertyClass($targetRefl, $property),
            'targetWritable' => $this->isWritable($targetRefl, $property),
        ];
    }

    /**
     * Mirrors what property_exists() and ReflectionClass::hasProperty() would answer for an instance of $sourceRefl,
     * the latter being asked on $lookupRefl as the mapper does when it iterates the target's properties.
     *
     * @return PropertyReadability::*
     */
    private function getReadability(\ReflectionClass $sourceRefl, \ReflectionClass $lookupRefl, string $property, ?\ReflectionProperty $declared, bool $hasMagicGet): int
    {
        if ($this->propertyAccessor) {
            return PropertyReadability::ACCESSOR;
        }

        // only a private property declared by a parent class is invisible to property_exists()
        $visible = $declared && (!$declared->isPrivate() || $declared->getDeclaringClass()->getName() === $sourceRefl->getName());

        if (!$visible) {
            if ($this->getPropertyFromHierarchy($lookupRefl, $property)) {
                return $hasMagicGet ? PropertyReadability::ALWAYS : ($declared ? PropertyReadability::NEVER : PropertyReadability::DYNAMIC);
            }

            return PropertyReadability::DYNAMIC;
        }

        if (!$lookupRefl->hasProperty($property)) {
            return PropertyReadability::ALWAYS;
        }

        if (!$lookupRefl->getProperty($property)->isPublic()) {
            return $hasMagicGet ? PropertyReadability::ALWAYS : PropertyReadability::NEVER;
        }

        return PropertyReadability::INITIALIZED;
    }

    /**
     * Returns the unconditional #[Map] declared on the target's own property when it explicitly
     * describes an inbound same-name copy, so its transform still applies even when the iteration
     * reads metadata from the source side. A mapping that omits "source" describes how the property
     * is read when its own class is the source, and must not be applied in this direction.
     * Conditional mappings are left to the regular same-name copy, which does not evaluate
     * conditions. Mappings carrying a target class are synthesized for another target and must not
     * leak their transform into this one.
     */
    private function getSameNameTargetMapping(object $target, string $property): ?Mapping
    {
        foreach ($this->metadataFactory->create($target, $property) as $mapping) {
            if (null === $mapping->if
                && !$mapping->targetClass
                && $property === $mapping->source
                && ($mapping->target ?? $property) === $property
            ) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Uninitialized typed properties are absent from the array cast of an object, whatever their visibility.
     */
    private function getArrayCastKey(\ReflectionProperty $property): string
    {
        return match (true) {
            $property->isPrivate() => "\0".$property->getDeclaringClass()->getName()."\0".$property->getName(),
            $property->isProtected() => "\0*\0".$property->getName(),
            default => $property->getName(),
        };
    }

    private function getPropertyClass(\ReflectionClass $targetRefl, string $property): ?string
    {
        $type = $this->getPropertyFromHierarchy($targetRefl, $property)?->getType();

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    private function isWritable(\ReflectionClass $targetRefl, string $property): bool
    {
        return $targetRefl->hasProperty($property) && $targetRefl->getProperty($property)->isPublic();
    }
}
