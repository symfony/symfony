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

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\ClassHierarchyTrait;

/**
 * Maps classes based on attributes found on the target class and its properties.
 *
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
final class ReverseClassObjectMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    use ClassHierarchyTrait;

    /**
     * @var array<string, list<Mapping>>
     */
    private array $attributesCache = [];

    /**
     * @param array<class-string, class-string|list<class-string>> $classMap Targets for a given source must be unique
     */
    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $objectMapperMetadataFactory,
        private readonly array $classMap,
    ) {
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        $class = $object::class;
        $key = $class.($property ? '.'.$property : '');

        if (isset($this->attributesCache[$key])) {
            return $this->attributesCache[$key];
        }

        $mappings = $this->objectMapperMetadataFactory->create($object, $property, $context);

        $targetClasses = [];
        for ($refl = new \ReflectionClass($object); $refl && !$targetClasses; $refl = $refl->getParentClass()) {
            $targetClasses = (array) ($this->classMap[$refl->getName()] ?? []);
        }

        if (!$targetClasses) {
            return $mappings;
        }

        if (!$property) {
            foreach ($targetClasses as $targetClass) {
                if (array_any($mappings, static fn (Mapping $m): bool => $m->target === $targetClass)) {
                    continue;
                }

                $matched = false;
                foreach ((new \ReflectionClass($targetClass))->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $map = $attribute->newInstance();
                    if ($map->source && is_a($class, $map->source, true)) {
                        $matched = true;
                        $mappings[] = new Mapping($targetClass, null, $map->if, $map->transform);
                    }
                }

                if (!$matched) {
                    $mappings[] = new Mapping($targetClass);
                }
            }

            return $this->attributesCache[$key] = $mappings;
        }

        foreach ($targetClasses as $targetClass) {
            foreach ($this->getAllProperties(new \ReflectionClass($targetClass)) as $reflProperty) {
                foreach ($reflProperty->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $map = $attribute->newInstance();
                    if ($map->source !== $property) {
                        continue;
                    }

                    $mappings[] = new Mapping($reflProperty->getName(), $map->source, $map->if, $map->transform, targetClass: $targetClass);
                }
            }
        }

        return $this->attributesCache[$key] = $mappings;
    }
}
