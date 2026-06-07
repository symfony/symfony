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
     * @var array<class-string, list<class-string>>
     */
    private readonly array $classMap;

    /**
     * @param array<class-string, class-string|list<class-string>> $classMap A single target
     *                                                                      per source is
     *                                                                      accepted for
     *                                                                      backward
     *                                                                      compatibility.
     */
    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $objectMapperMetadataFactory,
        array $classMap,
    ) {
        $normalized = [];
        foreach ($classMap as $source => $target) {
            $normalized[$source] = array_values((array) $target);
        }
        $this->classMap = $normalized;
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        $class = $object::class;
        // When several targets share one source, the active target is propagated
        // via $context to pick the right per-property metadata; cache per target too.
        $contextTarget = $context['target'] ?? null;
        $key = $class.($property ? '.'.$property : '').($contextTarget ? '.'.$contextTarget : '');

        if (isset($this->attributesCache[$key])) {
            return $this->attributesCache[$key];
        }

        $mappings = $this->objectMapperMetadataFactory->create($object, $property, $context);

        if (!$targets = $this->classMap[$class] ?? null) {
            return $mappings;
        }

        if (!$property) {
            foreach ($targets as $targetClass) {
                $mappings[] = new Mapping($targetClass);
            }

            return $this->attributesCache[$key] = $mappings;
        }

        $resolvedTarget = null !== $contextTarget && \in_array($contextTarget, $targets, true) ? $contextTarget : $targets[0];

        $refl = new \ReflectionClass($resolvedTarget);
        foreach ($refl->getProperties() as $reflProperty) {
            $attributes = $reflProperty->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                $map = $attribute->newInstance();
                // We're forcing the target on a reverse mapping to the property name, doesn't make sense without a source
                if (!$map->source) {
                    continue;
                }

                if ($map->source !== $property) {
                    continue;
                }

                $mappings[] = new Mapping($reflProperty->getName(), $map->source, $map->if, $map->transform);
            }
        }

        return $this->attributesCache[$key] = $mappings;
    }
}
