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

/**
 * Maps classes based on attributes found on the target's properties.
 *
 * @author Florent Blaison <florent.blaison@gmail.com>
 */
final class ReverseClassObjectMapperMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    /**
     * @var array<string, list<Mapping>>
     */
    private array $attributesCache = [];

    /**
     * @param array<class-string, class-string> $classMap
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

        if (!$targetClass = $this->classMap[$class] ?? null) {
            return $mappings;
        }

        if (!$property) {
            $mappings[] = new Mapping($targetClass);

            return $this->attributesCache[$key] = $mappings;
        }

        $refl = new \ReflectionClass($targetClass);
        foreach ($refl->getProperties() as $reflProperty) {
            $attributes = $reflProperty->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                $map = $attribute->newInstance();
                // When no source is given, the implicit source is the target property name.
                // This lets `#[Map(transform: ...)]` alone apply to same-named properties.
                $source = $map->source ?? $reflProperty->getName();
                $sourceRoot = explode('.', str_replace('?.', '.', $source))[0];

                if ($sourceRoot !== $property) {
                    continue;
                }

                $mappings[] = new Mapping($reflProperty->getName(), $source, $map->if, $map->transform);
            }
        }

        return $this->attributesCache[$key] = $mappings;
    }
}
