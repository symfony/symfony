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
use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * Maps a nested object to the class typing the property it is written into, based on the #[Map]
 * attributes that class declares.
 *
 * It only answers when the decorated factory reports no mapping for the object, and only when the
 * mapper describes the destination in the context. A property typed with a union, an intersection
 * or no type at all is left to the decorated factory.
 *
 * @author iz-ahmad <n.ahmad.web.cit22@gmail.com>
 */
final class PropertyTypeMappingMetadataFactory implements ObjectMapperMetadataFactoryInterface
{
    use ClassHierarchyTrait;

    /**
     * @var array<string, list<Mapping>>
     */
    private array $mappingsCache = [];

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $inner,
    ) {
    }

    public function create(object $object, ?string $property = null, array $context = []): array
    {
        $mappings = $this->inner->create($object, $property, $context);

        if ($mappings || null !== $property || !isset($context['target'], $context['target_property'])) {
            return $mappings;
        }

        $key = $context['target'].'::'.$context['target_property'].'::'.$object::class;

        return $this->mappingsCache[$key] ??= $this->createFromPropertyType($object, $context['target'], $context['target_property']);
    }

    /**
     * @return list<Mapping>
     */
    private function createFromPropertyType(object $object, string $targetClass, string $targetProperty): array
    {
        try {
            $property = $this->getPropertyFromHierarchy(new \ReflectionClass($targetClass), $targetProperty);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        $type = $property?->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $propertyClass = $type->getName();

        if ($object instanceof $propertyClass || !class_exists($propertyClass) && !interface_exists($propertyClass)) {
            return [];
        }

        $refl = new \ReflectionClass($propertyClass);
        $mappings = [];

        foreach ($refl->getAttributes(Map::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $map = $attribute->newInstance();

            if (!$map->source || !is_a($object, $map->source, true)) {
                continue;
            }

            // a transform returns the value to store, so the mapper never instantiates the class itself
            if (!$map->transform && !$refl->isInstantiable()) {
                throw new MappingException(\sprintf('Cannot infer a mapping target for "%s" from property "%s::$%s": class "%s" is not instantiable.', get_debug_type($object), $targetClass, $targetProperty, $propertyClass));
            }

            $mappings[] = new Mapping($propertyClass, null, $map->if, $map->transform);
        }

        return $mappings;
    }
}
