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
                // We're forcing the target on a reverse mapping to the property name, doesn't make sense without a source
                if ($map->source) {
                    $mappings[] = new Mapping($reflProperty->getName(), $map->source, $map->if, $map->transform);
                }
            }
        }

        return $this->attributesCache[$key] = $mappings;
    }
}
